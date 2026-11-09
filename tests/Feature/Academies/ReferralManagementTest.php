<?php

namespace Tests\Feature\Academies;

use App\Academies\Actions\ReconcileReferralRewards;
use App\Enums\AcademyRole;
use App\Models\Academy;
use App\Models\AcademyCycle;
use App\Models\AcademyInvoice;
use App\Models\AcademyMembership;
use App\Models\AcademyPayment;
use App\Models\Branch;
use App\Models\Enrollment;
use App\Models\Player;
use App\Models\Programme;
use App\Models\ProgrammeCycle;
use App\Models\Referral;
use App\Models\ReferralReward;
use App\Models\ReferralSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReferralManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_attribute_evaluate_approve_and_record_fixed_enrollment_reward(): void
    {
        [$user, $academy, $referrer, $referred] = $this->records();
        $this->asAcademy($user, $academy)->patch(route('academy.referrals.settings.update'), [
            'is_enabled' => true, 'qualifying_event' => 'enrollment', 'reward_type' => 'fixed',
            'reward_value' => '5000', 'eligible_payment_scope' => 'programme',
        ])->assertRedirect();
        $this->asAcademy($user, $academy)->post(route('academy.referrals.store'), [
            'referrer_player_id' => $referrer->id, 'referred_type' => 'player', 'referred_id' => $referred->id,
        ])->assertRedirect();

        $this->asAcademy($user, $academy)->post(route('academy.referrals.reconcile'))->assertRedirect();
        $this->asAcademy($user, $academy)->post(route('academy.referrals.reconcile'))->assertRedirect();
        $reward = ReferralReward::query()->firstOrFail();
        $this->assertSame(500000, $reward->reward_amount_minor);
        $this->assertDatabaseCount('referral_rewards', 1);

        $this->asAcademy($user, $academy)->patch(route('academy.referrals.rewards.approve', $reward))->assertRedirect();
        $this->asAcademy($user, $academy)->patch(route('academy.referrals.rewards.paid', $reward), [
            'payout_reference' => 'TRANSFER-1001', 'payout_notes' => 'Paid by bank transfer.',
        ])->assertRedirect();
        $this->assertDatabaseHas('referral_rewards', ['id' => $reward->id, 'status' => 'paid', 'payout_reference' => 'TRANSFER-1001']);
        $this->assertDatabaseHas('academy_expenses', [
            'academy_id' => $academy->id, 'referral_reward_id' => $reward->id,
            'category' => 'referral_reward', 'currency' => 'NGN',
            'amount_minor' => 500000, 'reference' => 'TRANSFER-1001',
        ]);
        $this->assertDatabaseHas('expense_categories', ['academy_id' => $academy->id, 'name' => 'Referral rewards']);
        $this->asAcademy($user, $academy)->patch(route('academy.referrals.rewards.paid', $reward), [
            'payout_reference' => 'TRANSFER-1002',
        ])->assertSessionHasErrors('reward');
        $this->assertDatabaseCount('academy_expenses', 1);

        $this->asAcademy($user, $academy)->get(route('academy.referrals.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('academies/referrals')->has('referrals', 1)->has('rewards', 1)->where('rewards.0.reward_amount', '5000.00'));
    }

    public function test_percentage_reward_uses_verified_programme_payment_and_is_idempotent(): void
    {
        [$user, $academy, $referrer, $referred, $offering] = $this->records();
        ReferralSetting::query()->create(['academy_id' => $academy->id, 'is_enabled' => true, 'qualifying_event' => 'first_verified_payment', 'reward_type' => 'percentage', 'reward_basis_points' => 1000, 'eligible_payment_scope' => 'programme']);
        $referral = Referral::query()->create(['academy_id' => $academy->id, 'referrer_player_id' => $referrer->id, 'referred_player_id' => $referred->id, 'status' => 'attributed', 'source' => 'admin', 'attributed_at' => now(), 'created_by_user_id' => $user->id]);
        $invoice = AcademyInvoice::query()->create(['academy_id' => $academy->id, 'number' => 'INV-REF-1', 'fee_name' => 'Tuition', 'player_id' => $referred->id, 'academy_cycle_id' => $offering->academy_cycle_id, 'programme_cycle_id' => $offering->id, 'programme_id' => $offering->programme_id, 'branch_id' => $offering->branch_id, 'purpose' => 'programme', 'status' => 'partially_paid', 'currency' => 'NGN', 'total_minor' => 10000000, 'paid_minor' => 4000000, 'written_off_minor' => 0, 'issued_at' => now(), 'due_on' => now()->addMonth(), 'created_by_user_id' => $user->id]);
        $payment = AcademyPayment::query()->create(['academy_id' => $academy->id, 'reference' => 'PAY-REF-1', 'player_id' => $referred->id, 'method' => 'bank_transfer', 'status' => 'verified', 'currency' => 'NGN', 'amount_minor' => 4000000, 'reversed_minor' => 0, 'received_on' => today(), 'recorded_by_user_id' => $user->id]);
        $payment->allocations()->create(['academy_id' => $academy->id, 'academy_invoice_id' => $invoice->id, 'amount_minor' => 4000000]);

        $service = app(ReconcileReferralRewards::class);
        $this->assertSame(1, $service->handle($academy));
        $this->assertSame(0, $service->handle($academy));
        $this->assertDatabaseHas('referral_rewards', ['referral_id' => $referral->id, 'qualifying_amount_minor' => 4000000, 'reward_amount_minor' => 400000]);
    }

    public function test_cross_tenant_reward_actions_and_self_referrals_are_rejected(): void
    {
        [$user, $academy, $referrer] = $this->records();
        [, $foreignAcademy, , , , $foreignReward] = $this->records(withReward: true);
        $this->asAcademy($user, $academy)->patch(route('academy.referrals.rewards.approve', $foreignReward))->assertNotFound();
        $this->asAcademy($user, $academy)->post(route('academy.referrals.store'), ['referrer_player_id' => $referrer->id, 'referred_type' => 'player', 'referred_id' => $referrer->id])->assertSessionHasErrors('referred_id');
        $this->assertNotSame($academy->id, $foreignAcademy->id);
    }

    /** @return array{User, Academy, Player, Player, ProgrammeCycle, ReferralReward|null} */
    private function records(bool $withReward = false): array
    {
        $user = User::factory()->create();
        $academy = Academy::factory()->for($user, 'creator')->create();
        AcademyMembership::factory()->for($user)->for($academy)->asRole(AcademyRole::OwnerAdmin)->create();
        $branch = Branch::factory()->for($academy)->create();
        $programme = Programme::factory()->for($academy)->create();
        $cycle = AcademyCycle::query()->create(['academy_id' => $academy->id, 'name' => '2027', 'type' => 'fixed', 'starts_on' => now()->subMonth(), 'ends_on' => now()->addYear(), 'status' => 'active']);
        $offering = ProgrammeCycle::query()->create(['academy_id' => $academy->id, 'academy_cycle_id' => $cycle->id, 'programme_id' => $programme->id, 'branch_id' => $branch->id, 'branch_scope_id' => $branch->id, 'fee_name' => 'Tuition', 'currency' => 'NGN', 'application_fee_minor' => 0, 'payment_mode' => 'one_off', 'enrollment_payment_rule' => 'full_payment', 'is_active' => true]);
        $referrer = Player::factory()->for($academy)->create();
        $referred = Player::factory()->for($academy)->create();
        Enrollment::factory()->for($referred)->for($programme)->create(['academy_id' => $academy->id, 'academy_cycle_id' => $cycle->id, 'programme_cycle_id' => $offering->id, 'branch_id' => $branch->id, 'status' => 'active', 'starts_on' => today(), 'ends_on' => now()->addYear()]);
        $reward = null;
        if ($withReward) {
            $referral = Referral::query()->create(['academy_id' => $academy->id, 'referrer_player_id' => $referrer->id, 'referred_player_id' => $referred->id, 'status' => 'qualified', 'source' => 'admin', 'attributed_at' => now(), 'qualified_at' => now(), 'created_by_user_id' => $user->id]);
            $reward = ReferralReward::query()->create(['academy_id' => $academy->id, 'referral_id' => $referral->id, 'event_key' => 'test-'.$academy->id, 'currency' => 'NGN', 'qualifying_amount_minor' => 0, 'reward_amount_minor' => 100000, 'status' => 'pending', 'earned_at' => now()]);
        }

        return [$user, $academy, $referrer, $referred, $offering, $reward];
    }

    private function asAcademy(User $user, Academy $academy): static
    {
        return $this->actingAs($user)->withSession(['active_academy_id' => $academy->id]);
    }
}
