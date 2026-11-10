<?php

namespace Tests\Feature\Academies;

use App\Enums\AcademyRole;
use App\Models\Academy;
use App\Models\AcademyCycle;
use App\Models\AcademyInvoice;
use App\Models\AcademyMembership;
use App\Models\Branch;
use App\Models\Player;
use App\Models\Programme;
use App\Models\ProgrammeCycle;
use App\Models\ScholarshipAward;
use App\Models\ScholarshipInvoiceAllocation;
use App\Models\ScholarshipScheme;
use App\Models\Sponsor;
use App\Models\SponsorCommitment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ScholarshipManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_sponsor_commitment_scheme_and_beneficiary_award(): void
    {
        [$owner, $academy] = $this->owner();
        [$invoice, $player, $cycle, $programme, $branch] = $this->invoice($academy);

        $this->asAcademy($owner, $academy)->post(route('academy.scholarships.sponsors.store'), [
            'name' => 'Future Foundation', 'type' => 'organisation', 'contact_name' => 'Ada Obi',
            'email' => 'ada@example.test', 'phone' => '08010000000', 'status' => 'active',
        ])->assertRedirect()->assertInertiaFlash('toast.message', 'Sponsor created.');
        $sponsor = Sponsor::query()->sole();

        $this->asAcademy($owner, $academy)->post(route('academy.scholarships.commitments.store'), [
            'sponsor_id' => $sponsor->id, 'academy_cycle_id' => $cycle->id,
            'name' => '2027 Scholarship Fund', 'amount' => '500000.00', 'status' => 'active',
        ])->assertRedirect()->assertInertiaFlash('toast.message', 'Sponsor commitment created.');
        $commitment = SponsorCommitment::query()->sole();

        $this->asAcademy($owner, $academy)->patch(route('academy.scholarships.commitments.update', $commitment), [
            'sponsor_id' => $sponsor->id, 'academy_cycle_id' => $cycle->id,
            'name' => '2027 Scholarship Fund', 'amount' => '550000.00', 'status' => 'active',
            'agreement_reference' => 'MOU-2027-01', 'benefits' => 'Recognition at the annual awards',
        ])->assertRedirect()->assertInertiaFlash('toast.message', 'Sponsor commitment updated.');
        $this->assertDatabaseHas('sponsor_commitments', [
            'id' => $commitment->id, 'committed_minor' => 55000000,
            'agreement_reference' => 'MOU-2027-01',
        ]);

        $this->asAcademy($owner, $academy)->post(route('academy.scholarships.schemes.store'), [
            'name' => 'Half tuition', 'funding_type' => 'sponsor', 'sponsor_id' => $sponsor->id,
            'coverage_type' => 'percentage', 'percentage' => '50', 'academy_cycle_id' => $cycle->id,
            'programme_id' => $programme->id, 'branch_id' => $branch->id, 'is_active' => true,
        ])->assertRedirect()->assertInertiaFlash('toast.message', 'Scholarship scheme created.');
        $scheme = ScholarshipScheme::query()->sole();
        $this->assertSame(5000, $scheme->percentage_basis_points);

        $this->asAcademy($owner, $academy)->post(route('academy.scholarships.awards.store'), [
            'scholarship_scheme_id' => $scheme->id, 'player_id' => $player->id,
            'sponsor_commitment_id' => $commitment->id,
        ])->assertRedirect()->assertInertiaFlash('toast.message', 'Scholarship awarded to beneficiary.');

        $this->assertDatabaseHas('scholarship_awards', [
            'academy_id' => $academy->id, 'player_id' => $player->id,
            'scholarship_scheme_id' => $scheme->id, 'sponsor_commitment_id' => $commitment->id,
        ]);
        $this->asAcademy($owner, $academy)->get(route('academy.scholarships.index'))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('academies/scholarships')->has('sponsors', 1)->has('schemes', 1)->has('awards', 1));
    }

    public function test_academy_funded_scholarship_reduces_personal_and_collectible_balance_without_fake_payment(): void
    {
        [$owner, $academy] = $this->owner();
        [$invoice, $player, $cycle] = $this->invoice($academy, 10000000);
        $scheme = ScholarshipScheme::query()->create([
            'academy_id' => $academy->id, 'name' => 'Academy bursary', 'funding_type' => 'academy',
            'coverage_type' => 'percentage', 'percentage_basis_points' => 5000, 'currency' => 'NGN',
            'academy_cycle_id' => $cycle->id, 'is_active' => true,
        ]);
        $award = ScholarshipAward::query()->create([
            'academy_id' => $academy->id, 'scholarship_scheme_id' => $scheme->id,
            'player_id' => $player->id, 'status' => 'active', 'approved_at' => now(), 'approved_by_user_id' => $owner->id,
        ]);

        $this->asAcademy($owner, $academy)->post(route('academy.scholarships.awards.allocations.store', $award), [
            'invoice_id' => $invoice->id,
        ])->assertRedirect()->assertInertiaFlash('toast.message', 'Scholarship applied to invoice.');
        $this->asAcademy($owner, $academy)->post(route('academy.scholarships.awards.allocations.store', $award), [
            'invoice_id' => $invoice->id,
        ])->assertRedirect();

        $invoice->refresh();
        $this->assertSame(5000000, $invoice->scholarship_concession_minor);
        $this->assertSame(5000000, $invoice->balanceMinor());
        $this->assertSame(5000000, $invoice->personalBalanceMinor());
        $this->assertSame(0, $invoice->paid_minor);
        $this->assertDatabaseCount('scholarship_invoice_allocations', 1);
        $this->assertDatabaseCount('academy_payments', 0);
    }

    public function test_sponsor_award_separates_responsibility_and_verified_contribution_posts_to_payment_ledger(): void
    {
        [$owner, $academy] = $this->owner();
        [$invoice, $player, $cycle] = $this->invoice($academy, 10000000);
        $sponsor = Sponsor::query()->create(['academy_id' => $academy->id, 'name' => 'Bright Futures', 'type' => 'organisation', 'status' => 'active']);
        $commitment = SponsorCommitment::query()->create([
            'academy_id' => $academy->id, 'sponsor_id' => $sponsor->id, 'academy_cycle_id' => $cycle->id,
            'name' => 'Annual pledge', 'currency' => 'NGN', 'committed_minor' => 6000000, 'status' => 'active',
        ]);
        $scheme = ScholarshipScheme::query()->create([
            'academy_id' => $academy->id, 'sponsor_id' => $sponsor->id, 'academy_cycle_id' => $cycle->id,
            'name' => 'Sponsored place', 'funding_type' => 'sponsor', 'coverage_type' => 'fixed',
            'fixed_amount_minor' => 6000000, 'currency' => 'NGN', 'is_active' => true,
        ]);
        $award = ScholarshipAward::query()->create([
            'academy_id' => $academy->id, 'scholarship_scheme_id' => $scheme->id, 'player_id' => $player->id,
            'sponsor_commitment_id' => $commitment->id, 'status' => 'active', 'approved_at' => now(),
        ]);
        $this->asAcademy($owner, $academy)->post(route('academy.scholarships.awards.allocations.store', $award), ['invoice_id' => $invoice->id])->assertRedirect();
        $allocation = ScholarshipInvoiceAllocation::query()->sole();

        $invoice->refresh();
        $this->assertSame(6000000, $invoice->sponsor_responsibility_minor);
        $this->assertSame(4000000, $invoice->personalBalanceMinor());
        $this->assertSame(10000000, $invoice->balanceMinor());

        $this->asAcademy($owner, $academy)->post(route('academy.invoices.payments.store', $invoice), [
            'amount' => '50000.00', 'method' => 'cash', 'received_on' => now()->toDateString(),
        ])->assertSessionHasErrors('amount');

        $this->asAcademy($owner, $academy)->post(route('academy.scholarships.contributions.store', $allocation), [
            'amount' => '30000.00', 'method' => 'bank_transfer', 'received_on' => now()->toDateString(),
            'external_reference' => 'SPONSOR-001',
        ])->assertRedirect()->assertInertiaFlash('toast.message', 'Sponsor contribution recorded and allocated.');

        $invoice->refresh();
        $this->assertSame(3000000, $invoice->paid_minor);
        $this->assertSame(3000000, $invoice->sponsor_paid_minor);
        $this->assertSame(7000000, $invoice->balanceMinor());
        $this->assertSame(4000000, $invoice->personalBalanceMinor());
        $this->assertSame(3000000, $allocation->fresh()->funded_minor);
        $this->assertSame(3000000, $commitment->fresh()->received_minor);
        $this->assertDatabaseHas('academy_payments', ['academy_id' => $academy->id, 'sponsor_id' => $sponsor->id, 'amount_minor' => 3000000]);
    }

    public function test_finance_officer_can_manage_module_but_cross_tenant_records_are_rejected(): void
    {
        $academy = Academy::factory()->create();
        $finance = User::factory()->create();
        AcademyMembership::factory()->for($academy)->for($finance)->asRole(AcademyRole::FinanceOfficer)->create();
        $foreignSponsor = Sponsor::query()->create(['academy_id' => Academy::factory()->create()->id, 'name' => 'Foreign', 'type' => 'organisation', 'status' => 'active']);

        $this->asAcademy($finance, $academy)->get(route('academy.scholarships.index'))->assertOk();
        $this->asAcademy($finance, $academy)->patch(route('academy.scholarships.sponsors.update', $foreignSponsor), [
            'name' => 'Changed', 'type' => 'organisation', 'status' => 'active',
        ])->assertNotFound();
        $this->assertSame('Foreign', $foreignSponsor->fresh()->name);
    }

    /** @return array{AcademyInvoice, Player, AcademyCycle, Programme, Branch} */
    private function invoice(Academy $academy, int $amountMinor = 10000000): array
    {
        $cycle = AcademyCycle::query()->create([
            'academy_id' => $academy->id, 'name' => '2027 Cycle', 'type' => 'fixed',
            'starts_on' => '2027-01-01', 'ends_on' => '2027-12-31', 'status' => 'active',
        ]);
        $programme = Programme::factory()->for($academy)->create();
        $branch = Branch::factory()->for($academy)->create();
        $player = Player::factory()->for($academy)->create();
        $offering = ProgrammeCycle::query()->create([
            'academy_id' => $academy->id, 'academy_cycle_id' => $cycle->id,
            'programme_id' => $programme->id, 'branch_id' => $branch->id, 'branch_scope_id' => $branch->id,
            'fee_name' => 'Tuition', 'currency' => 'NGN', 'application_fee_minor' => 0,
            'payment_mode' => 'one_off', 'enrollment_payment_rule' => 'full_payment', 'is_active' => true,
        ]);
        $invoice = AcademyInvoice::query()->create([
            'academy_id' => $academy->id, 'number' => 'INV-SCH-'.str()->random(6),
            'fee_name' => 'Tuition', 'player_id' => $player->id, 'academy_cycle_id' => $cycle->id,
            'programme_cycle_id' => $offering->id, 'programme_id' => $programme->id, 'branch_id' => $branch->id,
            'purpose' => 'programme', 'status' => 'issued', 'currency' => 'NGN',
            'total_minor' => $amountMinor, 'paid_minor' => 0, 'written_off_minor' => 0, 'issued_at' => now(),
        ]);
        $invoice->installments()->create([
            'academy_id' => $academy->id, 'name' => 'Full fee', 'amount_minor' => $amountMinor,
            'paid_minor' => 0, 'written_off_minor' => 0, 'sequence' => 1,
        ]);

        return [$invoice, $player, $cycle, $programme, $branch];
    }

    /** @return array{User, Academy} */
    private function owner(): array
    {
        $academy = Academy::factory()->create();
        $owner = User::factory()->create();
        AcademyMembership::factory()->for($academy)->for($owner)->asRole(AcademyRole::OwnerAdmin)->create();

        return [$owner, $academy];
    }

    private function asAcademy(User $user, Academy $academy): static
    {
        return $this->actingAs($user)->withSession(['active_academy_id' => $academy->id]);
    }
}
