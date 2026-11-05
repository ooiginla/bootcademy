<?php

namespace Tests\Feature\Academies;

use App\Enums\AcademyRole;
use App\Models\Academy;
use App\Models\AcademyCycle;
use App\Models\AcademyMembership;
use App\Models\Application;
use App\Models\Branch;
use App\Models\Programme;
use App\Models\ProgrammeCycle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CycleFeeManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_configures_fixed_and_rolling_cycles_with_only_one_active_cycle(): void
    {
        [$owner, $academy] = $this->member(AcademyRole::OwnerAdmin);
        $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->post(route('academy.cycles.store'), [
                'name' => '2026 Academic Year', 'type' => 'fixed', 'starts_on' => '2026-01-01',
                'ends_on' => '2026-12-31', 'status' => 'active',
            ])->assertRedirect()->assertInertiaFlash('toast.message', 'Billing cycle created.');
        $fixed = AcademyCycle::query()->whereBelongsTo($academy)->firstOrFail();
        $this->assertSame('active', $fixed->status->value);
        $this->assertNull($fixed->duration_months);

        $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->post(route('academy.cycles.store'), [
                'name' => 'Two-week Membership', 'type' => 'rolling',
                'duration_value' => 2, 'duration_unit' => 'weeks', 'status' => 'active',
            ])->assertRedirect();
        $rolling = AcademyCycle::query()->whereBelongsTo($academy)->where('type', 'rolling')->firstOrFail();
        $this->assertSame('active', $rolling->status->value);
        $this->assertSame(2, $rolling->duration_value);
        $this->assertSame('weeks', $rolling->duration_unit?->value);
        $this->assertSame('2026-11-12', $rolling->rollingEndDate(now()->setDate(2026, 10, 29)->startOfDay())->toDateString());
        $this->assertNull($rolling->starts_on);
        $this->assertSame('closed', $fixed->fresh()->status->value);
        $this->assertSame(1, $academy->cycles()->where('status', 'active')->count());
    }

    public function test_programme_cycle_stores_minor_unit_fee_breakdown_and_balanced_installments(): void
    {
        [$owner, $academy] = $this->member(AcademyRole::OwnerAdmin);
        $cycle = AcademyCycle::query()->create([
            'academy_id' => $academy->id, 'name' => '2026 Academic Year', 'type' => 'fixed',
            'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'status' => 'active',
        ]);
        $programme = Programme::factory()->for($academy)->create();
        $payload = [
            'programme_id' => $programme->id, 'fee_name' => 'Annual tuition', 'application_fee' => '5000.00',
            'payment_mode' => 'installments', 'enrollment_payment_rule' => 'first_installment', 'is_active' => true,
            'fee_items' => [
                ['name' => 'Tuition', 'amount' => '100000.00'],
                ['name' => 'Kit', 'amount' => '50000.00'],
            ],
            'installments' => [
                ['name' => 'First installment', 'amount' => '75000.00', 'due_on' => '2026-01-15'],
                ['name' => 'Final installment', 'amount' => '75000.00', 'due_on' => '2026-06-15'],
            ],
        ];
        $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->post(route('academy.cycles.offerings.store', $cycle), $payload)
            ->assertRedirect()->assertInertiaFlash('toast.message', 'Programme fees added to cycle.');

        $offering = ProgrammeCycle::query()->whereBelongsTo($academy)->firstOrFail();
        $this->assertSame(500000, $offering->application_fee_minor);
        $this->assertSame('Annual tuition', $offering->fee_name);
        $this->assertSame(15000000, (int) $offering->feeItems()->sum('amount_minor'));
        $this->assertSame(15000000, (int) $offering->installments()->sum('amount_minor'));
    }

    public function test_programme_fees_support_one_default_and_branch_specific_overrides(): void
    {
        [$owner, $academy] = $this->member(AcademyRole::OwnerAdmin);
        $cycle = AcademyCycle::query()->create([
            'academy_id' => $academy->id, 'name' => '2027', 'type' => 'fixed',
            'starts_on' => '2027-01-01', 'ends_on' => '2027-12-31', 'status' => 'active',
        ]);
        $programme = Programme::factory()->for($academy)->create();
        $branch = Branch::factory()->for($academy)->create();
        $payload = [
            'programme_id' => $programme->id, 'branch_id' => null, 'application_fee' => '1000.00',
            'payment_mode' => 'one_off', 'enrollment_payment_rule' => 'full_payment', 'is_active' => true,
            'fee_items' => [['name' => 'Tuition', 'amount' => '10000.00']], 'installments' => [],
        ];
        $member = $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id]);
        $member->post(route('academy.cycles.offerings.store', $cycle), $payload)->assertRedirect();
        $member->post(route('academy.cycles.offerings.store', $cycle), [
            ...$payload, 'branch_id' => $branch->id, 'application_fee' => '1500.00',
        ])->assertRedirect();

        $this->assertDatabaseHas('programme_cycles', [
            'academy_cycle_id' => $cycle->id, 'programme_id' => $programme->id,
            'branch_id' => null, 'branch_scope_id' => 0,
        ]);
        $this->assertDatabaseHas('programme_cycles', [
            'academy_cycle_id' => $cycle->id, 'programme_id' => $programme->id,
            'branch_id' => $branch->id, 'branch_scope_id' => $branch->id,
        ]);
        $member->post(route('academy.cycles.offerings.store', $cycle), $payload)
            ->assertSessionHasErrors('programme_id');
        $foreignBranch = Branch::factory()->for(Academy::factory()->create())->create();
        $member->post(route('academy.cycles.offerings.store', $cycle), [
            ...$payload, 'branch_id' => $foreignBranch->id,
        ])->assertSessionHasErrors('branch_id');
    }

    public function test_application_uses_branch_override_and_falls_back_to_default_fees(): void
    {
        [$owner, $academy] = $this->member(AcademyRole::OwnerAdmin);
        $cycle = AcademyCycle::query()->create([
            'academy_id' => $academy->id, 'name' => '2027', 'type' => 'fixed',
            'starts_on' => '2027-01-01', 'ends_on' => '2027-12-31', 'status' => 'active',
        ]);
        $programme = Programme::factory()->for($academy)->create();
        $overrideBranch = Branch::factory()->for($academy)->create();
        $fallbackBranch = Branch::factory()->for($academy)->create();
        $default = ProgrammeCycle::query()->create([
            'academy_id' => $academy->id, 'academy_cycle_id' => $cycle->id,
            'programme_id' => $programme->id, 'currency' => 'NGN', 'application_fee_minor' => 100000,
            'payment_mode' => 'one_off', 'enrollment_payment_rule' => 'full_payment', 'is_active' => true,
        ]);
        $override = ProgrammeCycle::query()->create([
            'academy_id' => $academy->id, 'academy_cycle_id' => $cycle->id,
            'programme_id' => $programme->id, 'branch_id' => $overrideBranch->id,
            'branch_scope_id' => $overrideBranch->id, 'currency' => 'NGN', 'application_fee_minor' => 150000,
            'payment_mode' => 'one_off', 'enrollment_payment_rule' => 'full_payment', 'is_active' => true,
        ]);
        $member = $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id]);
        $member->post(route('academy.applications.store'), [
            'first_name' => 'Ada', 'last_name' => 'Okafor', 'branch_id' => $overrideBranch->id,
            'programme_cycle_id' => $default->id,
        ])->assertRedirect();
        $branchApplication = Application::query()->where('first_name', 'Ada')->firstOrFail();
        $this->assertSame($override->id, $branchApplication->programme_cycle_id);
        $this->assertSame(150000, $branchApplication->invoices()->firstOrFail()->total_minor);

        $member->post(route('academy.applications.store'), [
            'first_name' => 'Tobi', 'last_name' => 'Okafor', 'branch_id' => $fallbackBranch->id,
            'programme_cycle_id' => $default->id,
        ])->assertRedirect();
        $fallbackApplication = Application::query()->where('first_name', 'Tobi')->firstOrFail();
        $this->assertSame($default->id, $fallbackApplication->programme_cycle_id);
        $this->assertSame(100000, $fallbackApplication->invoices()->firstOrFail()->total_minor);
    }

    public function test_installment_total_and_cycle_timing_are_validated(): void
    {
        [$owner, $academy] = $this->member(AcademyRole::OwnerAdmin);
        $cycle = AcademyCycle::query()->create([
            'academy_id' => $academy->id, 'name' => '2026', 'type' => 'fixed',
            'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'status' => 'active',
        ]);
        $programme = Programme::factory()->for($academy)->create();
        $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->post(route('academy.cycles.offerings.store', $cycle), [
                'programme_id' => $programme->id, 'application_fee' => '0',
                'payment_mode' => 'installments', 'enrollment_payment_rule' => 'full_payment', 'is_active' => true,
                'fee_items' => [['name' => 'Tuition', 'amount' => '100.00']],
                'installments' => [
                    ['name' => 'First', 'amount' => '40.00', 'due_on' => '2025-12-01'],
                    ['name' => 'Second', 'amount' => '40.00', 'due_on' => '2026-06-01'],
                ],
            ])->assertSessionHasErrors(['installments', 'installments.0.due_on']);
        $this->assertDatabaseCount('programme_cycles', 0);
    }

    public function test_cycle_and_offering_cannot_be_managed_across_tenants_or_by_coach(): void
    {
        [$owner, $academy] = $this->member(AcademyRole::OwnerAdmin);
        $foreignCycle = AcademyCycle::query()->create([
            'academy_id' => Academy::factory()->create()->id, 'name' => 'Foreign', 'type' => 'rolling',
            'duration_value' => 2, 'duration_unit' => 'weeks', 'status' => 'active',
        ]);
        $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->get(route('academy.cycles.show', $foreignCycle))->assertNotFound();

        [$coach, $coachAcademy] = $this->member(AcademyRole::Coach);
        $this->actingAs($coach)->withSession(['active_academy_id' => $coachAcademy->id])
            ->get(route('academy.cycles.index'))->assertForbidden();
    }

    /** @return array{User, Academy} */
    private function member(AcademyRole $role): array
    {
        $user = User::factory()->create();
        $academy = Academy::factory()->for($user, 'creator')->create();
        AcademyMembership::factory()->for($user)->for($academy)->asRole($role)->create();

        return [$user, $academy];
    }
}
