<?php

namespace Tests\Feature\Academies;

use App\Enums\AcademyRole;
use App\Models\Academy;
use App\Models\AcademyCycle;
use App\Models\AcademyInvoice;
use App\Models\AcademyMembership;
use App\Models\AcademyPayment;
use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Models\Enrollment;
use App\Models\Player;
use App\Models\Programme;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class M3CompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_partial_and_full_payment_reversals_are_explicit_and_reopen_the_invoice(): void
    {
        [$owner, $academy, $invoice] = $this->invoiceFixture();
        $this->asAcademy($owner, $academy)->post(route('academy.invoices.payments.store', $invoice), [
            'amount' => '100.00', 'method' => 'bank_transfer', 'received_on' => '2026-11-01', 'external_reference' => 'REV-TEST-1',
        ])->assertRedirect();
        $payment = AcademyPayment::query()->sole();

        $this->asAcademy($owner, $academy)->post(route('academy.payments.reversals.store', $payment), [
            'amount' => '40.00', 'reason' => 'Transfer was partially returned', 'effective_on' => '2026-11-02',
        ])->assertRedirect()->assertInertiaFlash('toast.message', 'Payment reversal recorded.');
        $this->assertDatabaseHas('academy_payment_adjustments', ['academy_id' => $academy->id, 'academy_payment_id' => $payment->id, 'amount_minor' => 4000]);
        $this->assertSame(4000, $payment->fresh()->reversed_minor);
        $this->assertSame(6000, $invoice->fresh()->paid_minor);
        $this->assertSame('partially_paid', $invoice->fresh()->status->value);
        $this->assertSame(6000, $invoice->installments()->firstOrFail()->paid_minor);

        $this->asAcademy($owner, $academy)->post(route('academy.payments.reversals.store', $payment), [
            'amount' => '60.00', 'reason' => 'Remaining amount returned', 'effective_on' => '2026-11-03',
        ])->assertRedirect();
        $this->assertSame('reversed', $payment->fresh()->status->value);
        $this->assertSame(0, $invoice->fresh()->paid_minor);
        $this->assertSame('issued', $invoice->fresh()->status->value);
        $this->asAcademy($owner, $academy)->get(route('academy.payments.receipt', $payment))
            ->assertOk()->assertSee($payment->reference)->assertSee('Reversed:');

        $this->asAcademy($owner, $academy)->post(route('academy.payments.reversals.store', $payment), [
            'amount' => '1.00', 'reason' => 'Too much', 'effective_on' => '2026-11-03',
        ])->assertSessionHasErrors('amount');
    }

    public function test_dashboard_metrics_are_filtered_by_branch_and_date_without_cross_tenant_data(): void
    {
        [$owner, $academy, $invoice, $branch, $programme, $player] = $this->invoiceFixture(true);
        Enrollment::factory()->for($player)->for($programme)->for($branch)->create([
            'academy_id' => $academy->id, 'status' => 'active', 'starts_on' => '2026-11-01',
        ]);
        $session = TrainingSession::query()->create([
            'academy_id' => $academy->id, 'branch_id' => $branch->id, 'programme_id' => $programme->id,
            'title' => 'Training', 'starts_at' => '2026-11-03 08:00:00', 'ends_at' => '2026-11-03 10:00:00',
            'status' => 'scheduled', 'created_by_user_id' => $owner->id,
        ]);
        $session->branches()->attach($branch->id, ['academy_id' => $academy->id]);
        AttendanceRecord::query()->create(['academy_id' => $academy->id, 'training_session_id' => $session->id, 'player_id' => $player->id, 'status' => 'present', 'marked_by_user_id' => $owner->id]);
        $this->asAcademy($owner, $academy)->post(route('academy.invoices.payments.store', $invoice), [
            'amount' => '50.00', 'method' => 'cash', 'received_on' => '2026-11-03',
        ])->assertRedirect();

        $this->asAcademy($owner, $academy)->get(route('dashboard', ['branch_id' => $branch->id, 'date_from' => '2026-11-01', 'date_to' => '2026-11-05']))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component('dashboard')
            ->where('operational.active_players', 1)->where('operational.new_enrollments', 1)
            ->where('operational.sessions', 1)->where('operational.attendance_rate', 100)
            ->where('operational.billed', '100.00')->where('operational.net_collected', '50.00')
            ->where('filters.branch_id', $branch->id));
    }

    public function test_csv_exports_are_private_tenant_scoped_and_audited(): void
    {
        [$owner, $academy, $invoice] = $this->invoiceFixture();
        $this->asAcademy($owner, $academy)->post(route('academy.invoices.payments.store', $invoice), [
            'amount' => '25.00', 'method' => 'cash', 'received_on' => '2026-11-01', 'external_reference' => 'OWN-PAYMENT',
        ]);
        [$foreignOwner, $foreignAcademy, $foreignInvoice] = $this->invoiceFixture();
        $this->asAcademy($foreignOwner, $foreignAcademy)->post(route('academy.invoices.payments.store', $foreignInvoice), [
            'amount' => '20.00', 'method' => 'cash', 'received_on' => '2026-11-01', 'external_reference' => 'FOREIGN-PAYMENT',
        ]);

        $response = $this->asAcademy($owner, $academy)->get(route('academy.exports.payments'));
        $response->assertOk()->assertDownload('payments.csv');
        $content = $response->streamedContent();
        $this->assertStringContainsString('OWN-PAYMENT', $content);
        $this->assertStringNotContainsString('FOREIGN-PAYMENT', $content);
        $this->assertDatabaseHas('audit_logs', ['academy_id' => $academy->id, 'action' => 'academy.export.payments']);

        $coach = User::factory()->create();
        AcademyMembership::factory()->for($coach)->for($academy)->asRole(AcademyRole::Coach)->create();
        $this->asAcademy($coach, $academy)->get(route('academy.exports.payments'))->assertForbidden();
    }

    public function test_finance_officer_can_use_m3_finance_without_academy_owner_permissions(): void
    {
        [$owner, $academy, $invoice] = $this->invoiceFixture();
        $this->asAcademy($owner, $academy)->post(route('academy.invoices.payments.store', $invoice), [
            'amount' => '25.00', 'method' => 'cash', 'received_on' => '2026-11-01',
        ]);
        $payment = AcademyPayment::query()->sole();
        $officer = User::factory()->create();
        AcademyMembership::factory()->for($officer)->for($academy)->asRole(AcademyRole::FinanceOfficer)->create();

        $this->asAcademy($officer, $academy)->get(route('academy.invoices.index'))->assertOk();
        $this->asAcademy($officer, $academy)->get(route('academy.payments.index'))->assertOk();
        $this->asAcademy($officer, $academy)->get(route('academy.expenses.index'))->assertOk();
        $this->asAcademy($officer, $academy)->get(route('dashboard'))->assertOk();
        $this->asAcademy($officer, $academy)->get(route('academy.exports.invoices'))->assertOk();
        $this->asAcademy($officer, $academy)->post(route('academy.payments.reversals.store', $payment), [
            'amount' => '5.00', 'reason' => 'Approved correction', 'effective_on' => '2026-11-02',
        ])->assertRedirect();
        $this->assertSame(500, $payment->fresh()->reversed_minor);
        $this->asAcademy($officer, $academy)->get(route('academy.branding.edit'))->assertForbidden();
    }

    /** @return array{User, Academy, AcademyInvoice, Branch, Programme, Player}|array{User, Academy, AcademyInvoice} */
    private function invoiceFixture(bool $extended = false): array
    {
        $owner = User::factory()->create();
        $academy = Academy::factory()->for($owner, 'creator')->create();
        AcademyMembership::factory()->for($owner)->for($academy)->asRole(AcademyRole::OwnerAdmin)->create();
        $cycle = AcademyCycle::query()->create(['academy_id' => $academy->id, 'name' => '2026', 'type' => 'fixed', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'status' => 'active']);
        $branch = Branch::factory()->for($academy)->create();
        $programme = Programme::factory()->for($academy)->create();
        $player = Player::factory()->for($academy)->create();
        $invoice = AcademyInvoice::query()->create([
            'academy_id' => $academy->id, 'number' => 'INV-'.strtoupper(fake()->unique()->lexify('??????')),
            'player_id' => $player->id, 'academy_cycle_id' => $cycle->id, 'programme_id' => $programme->id,
            'branch_id' => $branch->id, 'purpose' => 'custom', 'fee_name' => 'Training fee', 'status' => 'issued',
            'currency' => 'NGN', 'total_minor' => 10000, 'paid_minor' => 0, 'written_off_minor' => 0,
            'issued_at' => '2026-11-01 09:00:00', 'created_by_user_id' => $owner->id,
        ]);
        $invoice->installments()->create(['academy_id' => $academy->id, 'name' => 'Full payment', 'amount_minor' => 10000, 'paid_minor' => 0, 'written_off_minor' => 0, 'sequence' => 1]);

        return $extended ? [$owner, $academy, $invoice, $branch, $programme, $player] : [$owner, $academy, $invoice];
    }

    private function asAcademy(User $user, Academy $academy): static
    {
        return $this->actingAs($user)->withSession(['active_academy_id' => $academy->id]);
    }
}
