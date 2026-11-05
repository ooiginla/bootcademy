<?php

namespace Tests\Feature\Academies;

use App\Enums\AcademyRole;
use App\Models\Academy;
use App\Models\AcademyCycle;
use App\Models\AcademyCycleFee;
use App\Models\AcademyInvoice;
use App\Models\AcademyMembership;
use App\Models\Enrollment;
use App\Models\Player;
use App\Models\Programme;
use App\Models\ProgrammeCycle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InvoiceAdjustmentAndCycleFeeTest extends TestCase
{
    use RefreshDatabase;

    public function test_optional_fee_can_target_programmes_and_be_manually_booked_once(): void
    {
        [$owner, $academy] = $this->member();
        [$cycle, $programme, $offering, $player] = $this->enrolledPlayer($academy);

        $this->asAcademy($owner, $academy)->post(route('academy.cycles.fees.store', $cycle), [
            'name' => 'Medical screening',
            'category' => 'medical',
            'amount' => '25000.00',
            'is_compulsory' => false,
            'applies_to_all_programmes' => false,
            'is_active' => true,
            'programme_ids' => [$programme->id],
        ])->assertRedirect()->assertInertiaFlash('toast.message', 'Cycle fee created.');

        $fee = AcademyCycleFee::query()->where('academy_id', $academy->id)->firstOrFail();
        $this->assertSame(2500000, $fee->amount_minor);
        $this->assertFalse($fee->is_compulsory);
        $this->assertSame([$programme->id], $fee->programmes()->pluck('programmes.id')->all());
        $this->assertDatabaseCount('academy_invoices', 0);

        $this->asAcademy($owner, $academy)->post(route('academy.players.fees.store', $player), [
            'fee_id' => $fee->id,
        ])->assertRedirect()->assertInertiaFlash('toast.message', 'Fee invoice booked to player.');
        $this->asAcademy($owner, $academy)->post(route('academy.players.fees.store', $player), [
            'fee_id' => $fee->id,
        ])->assertRedirect();

        $invoice = AcademyInvoice::query()->where('academy_cycle_fee_id', $fee->id)->firstOrFail();
        $this->assertSame($player->id, $invoice->player_id);
        $this->assertSame($offering->id, $invoice->programme_cycle_id);
        $this->assertSame('custom', $invoice->purpose->value);
        $this->assertSame(2500000, $invoice->total_minor);
        $this->assertDatabaseCount('academy_invoices', 1);
        $this->asAcademy($owner, $academy)->get(route('academy.players.show', $player))
            ->assertInertia(fn (Assert $page) => $page
                ->where('player.cycles.0.invoices.0.fee_name', 'Medical screening'));
    }

    public function test_compulsory_fee_auto_books_existing_eligible_players_and_rejects_foreign_programmes(): void
    {
        [$owner, $academy] = $this->member();
        [$cycle, $programme, , $player] = $this->enrolledPlayer($academy);
        $foreignProgramme = Programme::factory()->for(Academy::factory()->create())->create();

        $this->asAcademy($owner, $academy)->post(route('academy.cycles.fees.store', $cycle), [
            'name' => 'Insurance', 'category' => 'insurance', 'amount' => '5000.00',
            'is_compulsory' => true, 'applies_to_all_programmes' => false, 'is_active' => true,
            'programme_ids' => [$foreignProgramme->id],
        ])->assertSessionHasErrors('programme_ids.0');
        $this->assertDatabaseCount('academy_cycle_fees', 0);

        $this->asAcademy($owner, $academy)->post(route('academy.cycles.fees.store', $cycle), [
            'name' => 'Insurance', 'category' => 'insurance', 'amount' => '5000.00',
            'is_compulsory' => true, 'applies_to_all_programmes' => false, 'is_active' => true,
            'programme_ids' => [$programme->id],
        ])->assertRedirect();

        $fee = AcademyCycleFee::query()->firstOrFail();
        $this->assertDatabaseHas('academy_invoices', [
            'academy_id' => $academy->id,
            'academy_cycle_fee_id' => $fee->id,
            'player_id' => $player->id,
            'total_minor' => 500000,
            'status' => 'issued',
        ]);
    }

    public function test_unpaid_issued_invoice_can_be_cancelled_but_adjusted_invoice_cannot(): void
    {
        [$owner, $academy] = $this->member();
        [, , $offering, $player] = $this->enrolledPlayer($academy);
        $invoice = $this->invoice($academy, $offering, $player, 'INV-CANCEL-1', 10000);

        $this->asAcademy($owner, $academy)->patch(route('academy.invoices.cancel', $invoice), [
            'reason' => 'Fee was issued in error.',
        ])->assertRedirect()->assertInertiaFlash('toast.message', 'Invoice cancelled.');
        $invoice->refresh();
        $this->assertSame('void', $invoice->status->value);
        $this->assertSame('Fee was issued in error.', $invoice->cancellation_reason);
        $this->assertSame($owner->id, $invoice->cancelled_by_user_id);
        $this->assertNotNull($invoice->cancelled_at);
        $this->asAcademy($owner, $academy)->get(route('academy.players.show', $player))
            ->assertInertia(fn (Assert $page) => $page
                ->where('player.total_billed', '0.00')
                ->where('player.total_owed', '0.00')
                ->where('player.cycles.0.invoices.0.number', 'INV-CANCEL-1')
                ->where('player.cycles.0.invoices.0.status', 'void')
                ->where('player.cycles.0.invoices.0.balance', '0.00'));

        $adjusted = $this->invoice($academy, $offering, $player, 'INV-CANCEL-2', 10000);
        $adjusted->update(['paid_minor' => 1000, 'status' => 'partially_paid']);
        $adjusted->installments()->firstOrFail()->update(['paid_minor' => 1000]);
        $this->asAcademy($owner, $academy)->patch(route('academy.invoices.cancel', $adjusted), [
            'reason' => 'Attempted cancellation.',
        ])->assertSessionHasErrors('reason');
        $this->assertSame('partially_paid', $adjusted->fresh()->status->value);

        [$foreignOwner, $foreignAcademy] = $this->member();
        $this->asAcademy($foreignOwner, $foreignAcademy)->patch(route('academy.invoices.cancel', $adjusted), [
            'reason' => 'Cross tenant attempt.',
        ])->assertNotFound();
    }

    public function test_partial_write_off_creates_expense_and_only_remaining_balance_can_be_paid(): void
    {
        [$owner, $academy] = $this->member();
        [, , $offering, $player] = $this->enrolledPlayer($academy);
        $invoice = $this->invoice($academy, $offering, $player, 'INV-WRITE-OFF', 10000);

        $this->asAcademy($owner, $academy)->post(route('academy.invoices.write-offs.store', $invoice), [
            'amount' => '100.01', 'reason' => 'Too much',
        ])->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('academy_invoice_write_offs', 0);

        $this->asAcademy($owner, $academy)->post(route('academy.invoices.write-offs.store', $invoice), [
            'amount' => '25.00', 'reason' => 'Approved hardship support',
        ])->assertRedirect()->assertInertiaFlash('toast.message', 'Invoice write-off recorded as an expense.');

        $invoice->refresh();
        $this->assertSame(2500, $invoice->written_off_minor);
        $this->assertSame(7500, $invoice->balanceMinor());
        $this->assertSame('partially_written_off', $invoice->status->value);
        $this->assertSame(2500, $invoice->installments()->firstOrFail()->written_off_minor);
        $this->assertDatabaseHas('academy_invoice_write_offs', [
            'academy_id' => $academy->id, 'academy_invoice_id' => $invoice->id,
            'amount_minor' => 2500, 'reason' => 'Approved hardship support',
        ]);
        $this->assertDatabaseHas('academy_expenses', [
            'academy_id' => $academy->id, 'academy_cycle_id' => $offering->academy_cycle_id,
            'category' => 'invoice_write_off', 'currency' => 'NGN', 'amount_minor' => 2500,
        ]);

        $this->asAcademy($owner, $academy)->post(route('academy.invoices.payments.store', $invoice), [
            'amount' => '75.01', 'method' => 'bank_transfer', 'received_on' => '2026-10-31',
            'external_reference' => 'OVER-AFTER-WRITEOFF',
        ])->assertSessionHasErrors('amount');
        $this->asAcademy($owner, $academy)->post(route('academy.invoices.payments.store', $invoice), [
            'amount' => '75.00', 'method' => 'bank_transfer', 'received_on' => '2026-10-31',
            'external_reference' => 'BALANCE-AFTER-WRITEOFF',
        ])->assertRedirect();

        $invoice->refresh();
        $this->assertSame(7500, $invoice->paid_minor);
        $this->assertSame(0, $invoice->balanceMinor());
        $this->assertSame('written_off', $invoice->status->value);
    }

    /** @return array{User, Academy} */
    private function member(): array
    {
        $user = User::factory()->create();
        $academy = Academy::factory()->for($user, 'creator')->create();
        AcademyMembership::factory()->for($user)->for($academy)->asRole(AcademyRole::OwnerAdmin)->create();

        return [$user, $academy];
    }

    /** @return array{AcademyCycle, Programme, ProgrammeCycle, Player} */
    private function enrolledPlayer(Academy $academy): array
    {
        $cycle = AcademyCycle::query()->create([
            'academy_id' => $academy->id, 'name' => '2026 Academic Year', 'type' => 'fixed',
            'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'status' => 'active',
        ]);
        $programme = Programme::factory()->for($academy)->create();
        $offering = ProgrammeCycle::query()->create([
            'academy_id' => $academy->id, 'academy_cycle_id' => $cycle->id,
            'programme_id' => $programme->id, 'currency' => 'NGN', 'application_fee_minor' => 0,
            'payment_mode' => 'one_off', 'enrollment_payment_rule' => 'full_payment', 'is_active' => true,
        ]);
        $player = Player::factory()->for($academy)->create();
        Enrollment::factory()->for($player)->create([
            'academy_id' => $academy->id,
            'academy_cycle_id' => $cycle->id, 'programme_cycle_id' => $offering->id,
            'programme_id' => $programme->id, 'status' => 'active',
            'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31',
        ]);

        return [$cycle, $programme, $offering, $player];
    }

    private function invoice(Academy $academy, ProgrammeCycle $offering, Player $player, string $number, int $totalMinor): AcademyInvoice
    {
        $invoice = AcademyInvoice::query()->create([
            'academy_id' => $academy->id, 'number' => $number, 'player_id' => $player->id,
            'academy_cycle_id' => $offering->academy_cycle_id, 'programme_cycle_id' => $offering->id,
            'programme_id' => $offering->programme_id, 'purpose' => 'programme', 'status' => 'issued',
            'currency' => 'NGN', 'total_minor' => $totalMinor, 'paid_minor' => 0,
            'written_off_minor' => 0, 'issued_at' => now(), 'due_on' => '2026-12-31',
        ]);
        $invoice->installments()->create([
            'academy_id' => $academy->id, 'name' => 'Full payment', 'amount_minor' => $totalMinor,
            'paid_minor' => 0, 'written_off_minor' => 0, 'due_on' => '2026-12-31', 'sequence' => 1,
        ]);

        return $invoice;
    }

    private function asAcademy(User $user, Academy $academy): static
    {
        return $this->actingAs($user)->withSession(['active_academy_id' => $academy->id]);
    }
}
