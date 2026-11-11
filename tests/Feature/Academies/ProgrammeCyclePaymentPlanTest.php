<?php

namespace Tests\Feature\Academies;

use App\Enums\AcademyRole;
use App\Models\Academy;
use App\Models\AcademyCycle;
use App\Models\AcademyCycleTerm;
use App\Models\AcademyMembership;
use App\Models\AuditLog;
use App\Models\Programme;
use App\Models\ProgrammeCycle;
use App\Models\ProgrammeCyclePaymentPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProgrammeCyclePaymentPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_fee_details_and_create_a_balanced_payment_plan(): void
    {
        [$owner, $academy] = $this->member(AcademyRole::OwnerAdmin);
        [$cycle, $offering] = $this->offering($academy);
        $offering->feeItems()->create([
            'academy_id' => $academy->id, 'name' => 'Annual programme fee',
            'description' => 'One annual payment',
            'amount_minor' => 10000000, 'sort_order' => 0,
        ]);
        $member = $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id]);

        $member->get(route('academy.cycles.offerings.show', [$cycle, $offering]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('academies/programme-fee-details')
                ->where('offering.programme_name', $offering->programme->name)
                ->where('base_fee_items.0.name', 'Annual programme fee')
                ->where('base_fee_items.0.description', 'One annual payment')
                ->where('base_fee_items.0.amount', '100000.00'));

        $member->post(route('academy.cycles.offerings.payment-plans.store', [$cycle, $offering]), [
            'name' => 'Two payments',
            'description' => 'Two equal repayments, six months apart.',
            'sort_order' => 20,
            'academy_cycle_term_id' => null,
            'cohort_id' => null,
            'is_default' => true,
            'is_active' => true,
            'fee_items' => [
                ['name' => 'Annual programme fee', 'description' => '₦50,000 every six months', 'amount' => '100000.00'],
                ['name' => 'Two-payment plan adjustment', 'description' => 'Installment administration', 'amount' => '10000.00'],
            ],
            'installments' => [
                ['name' => 'First payment', 'amount' => '55000.00', 'due_on' => '2028-01-15'],
                ['name' => 'Second payment', 'amount' => '55000.00', 'due_on' => '2028-07-15'],
            ],
        ])->assertRedirect()->assertInertiaFlash('toast.message', 'Payment plan created.');

        $plan = ProgrammeCyclePaymentPlan::query()->where('programme_cycle_id', $offering->id)->sole();
        $this->assertSame(11000000, $plan->total_minor);
        $this->assertSame(20, $plan->sort_order);
        $this->assertTrue($plan->is_default);
        $this->assertSame(2, $plan->items()->count());
        $this->assertSame('₦50,000 every six months', $plan->items()->orderBy('sort_order')->firstOrFail()->description);
        $this->assertSame(2, $plan->installments()->count());
        $this->assertSame(11000000, (int) $plan->installments()->sum('amount_minor'));
        $this->assertDatabaseHas(AuditLog::class, [
            'academy_id' => $academy->id,
            'actor_user_id' => $owner->id,
            'action' => 'academy.programme_payment_plan.created',
        ]);

        $earlier = $offering->paymentPlans()->create([
            'academy_id' => $academy->id,
            'term_scope_id' => 0,
            'cohort_scope_id' => 0,
            'name' => 'Pay once',
            'description' => 'One payment.',
            'sort_order' => 10,
            'currency' => 'NGN',
            'total_minor' => 10000000,
            'is_default' => false,
            'is_active' => true,
            'is_legacy' => false,
            'is_standard' => false,
        ]);
        $earlier->items()->create([
            'academy_id' => $academy->id,
            'name' => 'Annual programme fee',
            'description' => 'One payment',
            'amount_minor' => 10000000,
            'sort_order' => 0,
        ]);
        $earlier->installments()->create([
            'academy_id' => $academy->id,
            'name' => 'Full payment',
            'amount_minor' => 10000000,
            'due_on' => '2028-01-15',
            'sequence' => 1,
        ]);

        $member->get(route('academy.cycles.offerings.show', [$cycle, $offering]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('plans.0.name', 'Pay once')
                ->where('plans.0.sort_order', 10)
                ->where('plans.1.name', 'Two payments')
                ->where('plans.1.sort_order', 20));
    }

    public function test_plan_rejects_mismatched_repayments_and_cross_tenant_access(): void
    {
        [$owner, $academy] = $this->member(AcademyRole::OwnerAdmin);
        [$cycle, $offering] = $this->offering($academy);
        $offering->feeItems()->create([
            'academy_id' => $academy->id, 'name' => 'Programme fee',
            'amount_minor' => 10000000, 'sort_order' => 0,
        ]);

        $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->post(route('academy.cycles.offerings.payment-plans.store', [$cycle, $offering]), [
                'name' => 'Broken monthly plan', 'is_default' => false, 'is_active' => true,
                'fee_items' => [['name' => 'Programme fee', 'amount' => '120000.00']],
                'installments' => [
                    ['name' => 'Payment 1', 'amount' => '10000.00', 'due_on' => '2028-01-15'],
                    ['name' => 'Payment 2', 'amount' => '10000.00', 'due_on' => '2028-02-15'],
                ],
            ])->assertSessionHasErrors('installments');

        $this->assertDatabaseCount('programme_cycle_payment_plans', 0);

        [$foreignOwner, $foreignAcademy] = $this->member(AcademyRole::OwnerAdmin);
        $this->actingAs($foreignOwner)->withSession(['active_academy_id' => $foreignAcademy->id])
            ->get(route('academy.cycles.offerings.show', [$cycle, $offering]))
            ->assertNotFound();
    }

    public function test_term_fee_details_only_show_the_selected_term_scope(): void
    {
        [$owner, $academy] = $this->member(AcademyRole::OwnerAdmin);
        [$cycle, $offering] = $this->offering($academy);
        $term = AcademyCycleTerm::query()->create([
            'academy_id' => $academy->id, 'academy_cycle_id' => $cycle->id,
            'name' => 'Term 1', 'starts_on' => '2028-01-01', 'ends_on' => '2028-04-30',
            'sort_order' => 1, 'is_default' => true, 'is_active' => true,
        ]);
        $offering->feeItems()->create([
            'academy_id' => $academy->id, 'academy_cycle_term_id' => $term->id,
            'name' => 'Term 1 tuition', 'amount_minor' => 4000000, 'sort_order' => 0,
        ]);
        $offering->feeItems()->create([
            'academy_id' => $academy->id, 'name' => 'Whole-cycle registration',
            'amount_minor' => 500000, 'sort_order' => 1,
        ]);

        $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->get(route('academy.cycles.offerings.show', [$cycle, $offering]).'?term_id='.$term->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('scope.term_name', 'Term 1')
                ->where('scope.starts_on', '2028-01-01')
                ->has('base_fee_items', 1)
                ->where('base_fee_items.0.name', 'Term 1 tuition'));
    }

    /** @return array{AcademyCycle, ProgrammeCycle} */
    private function offering(Academy $academy): array
    {
        $cycle = AcademyCycle::query()->create([
            'academy_id' => $academy->id, 'name' => '2028 Academy Year', 'type' => 'fixed',
            'starts_on' => '2028-01-01', 'ends_on' => '2028-12-31', 'status' => 'active',
        ]);
        $programme = Programme::factory()->for($academy)->create(['name' => 'Under 17']);
        $offering = ProgrammeCycle::query()->create([
            'academy_id' => $academy->id, 'academy_cycle_id' => $cycle->id,
            'programme_id' => $programme->id, 'fee_name' => 'Annual tuition',
            'currency' => 'NGN', 'application_fee_minor' => 0,
            'payment_mode' => 'one_off', 'enrollment_payment_rule' => 'full_payment', 'is_active' => true,
        ]);

        return [$cycle, $offering];
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
