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
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InvoiceRegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_defaults_to_active_cycle_and_summary_excludes_cancelled_invoice_values(): void
    {
        [$owner, $academy] = $this->member();
        [$active, $historical, $programme, $branch, $player] = $this->financeRecords($academy);

        $this->invoice($academy, $owner, $active, $programme, $branch, $player, [
            'number' => 'INV-ACTIVE', 'fee_name' => 'Annual tuition', 'status' => 'partially_paid',
            'total_minor' => 10000, 'paid_minor' => 2000, 'written_off_minor' => 1000,
            'issued_at' => '2026-10-10 10:00:00',
        ]);
        $this->invoice($academy, $owner, $active, $programme, $branch, $player, [
            'number' => 'INV-CANCELLED', 'fee_name' => 'Insurance', 'status' => 'void',
            'total_minor' => 5000, 'paid_minor' => 0, 'written_off_minor' => 0,
            'issued_at' => '2026-10-11 10:00:00',
        ]);
        $this->invoice($academy, $owner, $historical, $programme, $branch, $player, [
            'number' => 'INV-HISTORICAL', 'fee_name' => 'Annual tuition', 'status' => 'paid',
            'total_minor' => 20000, 'paid_minor' => 20000, 'written_off_minor' => 0,
            'issued_at' => '2025-09-10 10:00:00',
        ]);

        $this->asAcademy($owner, $academy)->get(route('academy.invoices.index'))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('academies/invoices')
            ->where('filters.cycle_id', $active->id)
            ->where('summary.billed', '100.00')
            ->where('summary.paid', '20.00')
            ->where('summary.owed', '70.00')
            ->where('summary.written_off', '10.00')
            ->has('invoices.data', 2)
            ->where('invoices.data.0.number', 'INV-CANCELLED')
            ->where('invoices.data.0.status', 'void')
            ->where('invoices.data.0.owed', '0.00')
            ->where('invoices.data.1.number', 'INV-ACTIVE'));
    }

    public function test_all_filters_update_rows_and_summary_from_the_same_tenant_scoped_query(): void
    {
        [$owner, $academy] = $this->member();
        [$active, $historical, $programme, $branch, $player] = $this->financeRecords($academy);
        $this->invoice($academy, $owner, $active, $programme, $branch, $player, [
            'number' => 'INV-ACTIVE', 'fee_name' => 'Insurance', 'status' => 'issued',
            'total_minor' => 5000, 'paid_minor' => 0, 'written_off_minor' => 0,
            'issued_at' => '2026-10-10 10:00:00',
        ]);
        $this->invoice($academy, $owner, $historical, $programme, $branch, $player, [
            'number' => 'INV-MATCH', 'fee_name' => 'Annual tuition', 'status' => 'paid',
            'total_minor' => 20000, 'paid_minor' => 20000, 'written_off_minor' => 0,
            'issued_at' => '2025-09-10 10:00:00',
        ]);

        $this->asAcademy($owner, $academy)->get(route('academy.invoices.index', [
            'cycle_id' => $historical->id, 'programme_id' => $programme->id,
            'branch_id' => $branch->id, 'fee_name' => 'Annual tuition', 'status' => 'paid',
            'date_from' => '2025-09-01', 'date_to' => '2025-09-30',
        ]))->assertInertia(fn (Assert $page) => $page
            ->has('invoices.data', 1)
            ->where('invoices.data.0.number', 'INV-MATCH')
            ->where('summary.billed', '200.00')
            ->where('summary.paid', '200.00')
            ->where('summary.owed', '0.00')
            ->where('summary.written_off', '0.00')
            ->where('filters.fee_name', 'Annual tuition')
            ->where('filters.status', 'paid'));

        $this->asAcademy($owner, $academy)->get('/app/finance/invoices?cycle_id=')
            ->assertInertia(fn (Assert $page) => $page
                ->has('invoices.data', 2)
                ->where('summary.billed', '250.00')
                ->where('summary.paid', '200.00')
                ->where('summary.owed', '50.00'));
    }

    public function test_register_is_forbidden_to_coach_and_never_returns_another_academys_invoices(): void
    {
        [$owner, $academy] = $this->member();
        $this->financeRecords($academy);
        [$foreignOwner, $foreignAcademy] = $this->member();
        [$foreignCycle, , $foreignProgramme, $foreignBranch, $foreignPlayer] = $this->financeRecords($foreignAcademy);
        $this->invoice($foreignAcademy, $foreignOwner, $foreignCycle, $foreignProgramme, $foreignBranch, $foreignPlayer, [
            'number' => 'INV-FOREIGN', 'fee_name' => 'Hidden fee', 'status' => 'issued',
            'total_minor' => 9000, 'paid_minor' => 0, 'written_off_minor' => 0, 'issued_at' => now(),
        ]);

        $this->asAcademy($owner, $academy)->get('/app/finance/invoices?cycle_id=')
            ->assertInertia(fn (Assert $page) => $page
                ->has('invoices.data', 0)
                ->where('summary.billed', '0.00')
                ->where('fee_names', []));

        $coach = User::factory()->create();
        AcademyMembership::factory()->for($coach)->for($academy)->asRole(AcademyRole::Coach)->create();
        $this->asAcademy($coach, $academy)->get(route('academy.invoices.index'))->assertForbidden();
    }

    /** @return array{User, Academy} */
    private function member(): array
    {
        $user = User::factory()->create();
        $academy = Academy::factory()->for($user, 'creator')->create();
        AcademyMembership::factory()->for($user)->for($academy)->asRole(AcademyRole::OwnerAdmin)->create();

        return [$user, $academy];
    }

    /** @return array{AcademyCycle, AcademyCycle, Programme, Branch, Player} */
    private function financeRecords(Academy $academy): array
    {
        $active = AcademyCycle::query()->create([
            'academy_id' => $academy->id, 'name' => '2026 Active Cycle', 'type' => 'fixed',
            'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'status' => 'active',
        ]);
        $historical = AcademyCycle::query()->create([
            'academy_id' => $academy->id, 'name' => '2025 Historical Cycle', 'type' => 'fixed',
            'starts_on' => '2025-01-01', 'ends_on' => '2025-12-31', 'status' => 'closed',
        ]);

        return [
            $active, $historical, Programme::factory()->for($academy)->create(),
            Branch::factory()->for($academy)->create(), Player::factory()->for($academy)->create(),
        ];
    }

    /** @param array<string, mixed> $overrides */
    private function invoice(
        Academy $academy,
        User $owner,
        AcademyCycle $cycle,
        Programme $programme,
        Branch $branch,
        Player $player,
        array $overrides,
    ): AcademyInvoice {
        return AcademyInvoice::query()->create([
            'academy_id' => $academy->id, 'player_id' => $player->id,
            'academy_cycle_id' => $cycle->id, 'programme_id' => $programme->id, 'branch_id' => $branch->id,
            'purpose' => 'custom', 'currency' => 'NGN', 'issued_at' => now(),
            'created_by_user_id' => $owner->id, ...$overrides,
        ]);
    }

    private function asAcademy(User $user, Academy $academy): static
    {
        return $this->actingAs($user)->withSession(['active_academy_id' => $academy->id]);
    }
}
