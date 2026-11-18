<?php

namespace Tests\Feature\Platform;

use App\Enums\BillingInterval;
use App\Enums\SubscriptionStatus;
use App\Models\Academy;
use App\Models\AcademyMembership;
use App\Models\AcademyPlanPrice;
use App\Models\AcademySubscriptionPeriod;
use App\Models\AuditLog;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Platform\AcademyEntitlements;
use App\Platform\Subscriptions\AddSubscriptionPeriod;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PlanAndSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_launch_plans_follow_the_pricing_context(): void
    {
        $this->seed(PlanSeeder::class);

        $this->assertDatabaseHas(Plan::class, [
            'code' => 'essential', 'monthly_price_minor' => 2_500_000, 'quarterly_price_minor' => 7_125_000,
            'annual_price_minor' => 27_000_000, 'active_player_limit' => 75, 'branch_limit' => 1, 'staff_account_limit' => 5,
            'is_recommended' => false,
        ]);
        $this->assertDatabaseHas(Plan::class, [
            'code' => 'professional', 'monthly_price_minor' => 5_000_000, 'quarterly_price_minor' => 14_250_000,
            'annual_price_minor' => 54_000_000, 'active_player_limit' => 250, 'branch_limit' => 3, 'staff_account_limit' => 20,
            'is_recommended' => true,
        ]);
        $this->assertDatabaseHas(Plan::class, [
            'code' => 'enterprise', 'monthly_price_minor' => 10_000_000, 'annual_price_minor' => 108_000_000,
            'active_player_limit' => 750, 'branch_limit' => 10, 'staff_account_limit' => 60,
        ]);
        $plans = Plan::query()->get()->keyBy('code');
        $this->assertSame(['api_access' => false, 'custom_domain' => false], $plans['professional']->flags());
        $this->assertSame(['api_access' => true, 'custom_domain' => true], $plans['enterprise']->flags());
        $settings = PlatformSetting::current();
        $this->assertSame($plans['enterprise']->id, $settings->trial_plan_id);
        $this->assertSame($plans['essential']->id, $settings->post_trial_plan_id);
        $this->assertSame(30, $settings->trial_days);
    }

    public function test_platform_admin_edits_plan_prices_limits_and_features(): void
    {
        $admin = $this->admin();
        $plan = $this->plan('professional');

        $this->actingAs($admin)->patch(route('platform.plans.update', $plan), [
            'name' => 'Growth', 'description' => 'For growing clubs.',
            'monthly_ngn' => 30_000, 'quarterly_ngn' => 90_000, 'annual_ngn' => 324_000,
            'active_player_limit' => 150, 'branch_limit' => '', 'staff_account_limit' => 12,
            'is_recommended' => true, 'migration_support' => 'Guided', 'support_level' => 'Priority email',
            'flags' => ['api_access' => '1', 'custom_domain' => '0'],
            'is_active' => true,
        ])->assertRedirect(route('platform.plans.index'))
            ->assertInertiaFlash('toast.message', 'Plan updated.');

        $plan->refresh()->load('features');
        $this->assertSame('Growth', $plan->name);
        $this->assertSame(3_000_000, $plan->monthly_price_minor);
        $this->assertNull($plan->branch_limit);
        $this->assertSame(12, $plan->staff_account_limit);
        $this->assertSame(['api_access' => true, 'custom_domain' => false], $plan->flags());
        $this->assertDatabaseHas(AuditLog::class, ['actor_user_id' => $admin->id, 'action' => 'platform.plan.updated']);
    }

    public function test_only_platform_admins_manage_plans_and_tenant_subscriptions(): void
    {
        $user = User::factory()->create();
        $academy = Academy::factory()->create();
        $plan = $this->plan('essential');

        $this->actingAs($user)->patch(route('platform.plans.update', $plan), ['name' => 'Blocked'])->assertForbidden();
        $this->actingAs($user)->patch(route('platform.academies.subscription.update', $academy), ['status' => 'suspended'])->assertForbidden();
        $this->actingAs($user)->post(route('platform.academies.subscription.periods.store', $academy), [
            'plan_id' => $plan->id, 'ends_on' => now()->addYear()->toDateString(),
        ])->assertForbidden();
        $this->actingAs($user)->put(route('platform.academies.subscription.prices.update', [$academy, $plan]), [
            'monthly_ngn' => 1, 'quarterly_ngn' => 1, 'annual_ngn' => 1,
        ])->assertForbidden();
        $this->actingAs($user)->get(route('platform.subscriptions.index'))->assertForbidden();
        $this->actingAs($user)->patch(route('platform.settings.trial.update'), ['trial_days' => 365])->assertForbidden();
        $this->actingAs($user)->patch(route('platform.settings.settlement-account.update'), [])->assertForbidden();

        $this->assertSame(SubscriptionStatus::Active, $academy->subscription?->status);
        $this->assertSame(0, AcademyPlanPrice::query()->count());
        $this->assertSame(1, AcademySubscriptionPeriod::query()->where('academy_id', $academy->id)->count());
    }

    public function test_suspending_an_academy_locks_the_app_even_during_its_trial(): void
    {
        $admin = $this->admin();
        $owner = User::factory()->create();
        $academy = Academy::factory()->create();
        AcademyMembership::factory()->for($owner)->for($academy)->create();

        $this->actingAs($admin)->patch(route('platform.academies.subscription.update', $academy), ['status' => 'suspended'])
            ->assertRedirect()->assertInertiaFlash('toast.message', 'Academy subscription updated.');
        $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])->get(route('dashboard'))
            ->assertRedirect(route('academy.subscription.show'));
        $this->assertSame('suspended', AcademyEntitlements::for($academy)->state());

        $this->actingAs($admin)->patch(route('platform.academies.subscription.update', $academy), ['status' => 'active']);
        $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])->get(route('dashboard'))->assertOk();
        $this->assertDatabaseHas('subscription_events', ['academy_id' => $academy->id, 'event' => 'subscription.status.changed']);
    }

    public function test_granted_access_starts_after_paid_time_and_ends_the_trial(): void
    {
        $admin = $this->admin();
        $academy = Academy::factory()->create();
        $essential = $this->plan('essential');

        $this->actingAs($admin)->post(route('platform.academies.subscription.periods.store', $academy), [
            'plan_id' => $essential->id, 'ends_on' => now()->addMonths(2)->toDateString(), 'note' => 'Paid by transfer',
        ])->assertSessionHasNoErrors()->assertInertiaFlash('toast.message', 'Access granted.');

        $entitlements = AcademyEntitlements::for($academy);
        $this->assertSame('active', $entitlements->state());
        $this->assertSame('essential', $entitlements->plan()?->code);
        $granted = AcademySubscriptionPeriod::query()->where('kind', 'granted')->sole();
        $this->assertSame($admin->id, $granted->granted_by_user_id);

        // A second grant must reach past the time already granted.
        $this->actingAs($admin)->post(route('platform.academies.subscription.periods.store', $academy), [
            'plan_id' => $essential->id, 'ends_on' => now()->addMonth()->toDateString(),
        ])->assertSessionHasErrors('ends_on');
        $this->actingAs($admin)->post(route('platform.academies.subscription.periods.store', $academy), [
            'plan_id' => $this->plan('professional')->id, 'ends_on' => now()->addMonths(5)->toDateString(),
        ])->assertSessionHasNoErrors();
        $next = AcademySubscriptionPeriod::query()->where('kind', 'granted')->latest('id')->first();
        $this->assertTrue($next?->starts_at->equalTo($granted->ends_at));
    }

    public function test_platform_admin_sets_and_removes_one_academys_prices(): void
    {
        $admin = $this->admin();
        $academy = Academy::factory()->create();
        $plan = $this->plan('enterprise');

        $this->actingAs($admin)->put(route('platform.academies.subscription.prices.update', [$academy, $plan]), [
            'monthly_ngn' => 60_000, 'quarterly_ngn' => 170_000, 'annual_ngn' => 600_000,
        ])->assertSessionHasNoErrors();
        $this->assertSame(17_000_000, $plan->priceFor(BillingInterval::Quarterly, $academy));
        $this->assertSame(28_500_000, $plan->priceFor(BillingInterval::Quarterly));

        $this->actingAs($admin)->get(route('platform.academies.subscription.edit', $academy))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('platform/academies/subscription')
                ->where('state', 'trialing')
                ->where('plans.2.custom.annual', 60_000_000)
                ->where('plans.0.custom', null));

        $this->actingAs($admin)->put(route('platform.academies.subscription.prices.update', [$academy, $plan]), [
            'monthly_ngn' => 0, 'quarterly_ngn' => 1, 'annual_ngn' => 1,
        ])->assertSessionHasErrors('monthly_ngn');
        $this->actingAs($admin)->delete(route('platform.academies.subscription.prices.destroy', [$academy, $plan]))->assertRedirect();
        $this->assertSame(10_000_000, $plan->priceFor(BillingInterval::Monthly, $academy));
        $this->assertDatabaseHas(AuditLog::class, ['action' => 'academy.subscription.price-set']);
        $this->assertDatabaseHas(AuditLog::class, ['action' => 'academy.subscription.price-removed']);
    }

    public function test_tenant_subscriptions_lists_payments_and_totals_revenue(): void
    {
        $admin = $this->admin();
        $paying = Academy::factory()->create(['name' => 'Paying Academy']);
        $trial = Academy::factory()->create();
        $lapsed = Academy::factory()->create();
        AcademySubscriptionPeriod::query()->where('academy_id', $lapsed->id)->update(['ends_at' => now()->subDay()]);
        $essential = $this->plan('essential');
        $verified = $this->payment($paying, $essential, 'verified', 2_500_000, 48224);
        $this->payment($paying, $essential, 'pending', 2_500_000, 48224);
        app(AddSubscriptionPeriod::class)->handle($paying, $essential, 'paid', BillingInterval::Monthly, $verified, null, Request::create('/'));

        $this->actingAs($admin)->get(route('platform.subscriptions.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('platform/subscriptions')
                ->where('summary.revenue_minor', 2_500_000)
                ->where('summary.revenue_this_month_minor', 2_500_000)
                ->where('summary.payments_count', 1)
                ->where('summary.paying_academies', 1)
                ->where('summary.trialing_academies', 1)
                ->where('summary.lapsed_academies', 1)
                ->has('payments.data', 1)
                ->where('payments.data.0.academy.name', 'Paying Academy')
                ->where('payments.data.0.surcharge_minor', 48224)
                ->whereNot('payments.data.0.period_ends_at', null));
        $this->actingAs($admin)->get(route('platform.subscriptions.index', ['status' => 'all']))
            ->assertInertia(fn (Assert $page) => $page->has('payments.data', 2));
        $this->actingAs($admin)->get(route('platform.subscriptions.index', ['status' => 'all', 'search' => 'nobody']))
            ->assertInertia(fn (Assert $page) => $page->has('payments.data', 0));
        $this->assertNotSame($trial->id, $paying->id);
    }

    public function test_trial_settings_apply_to_academies_created_afterwards(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->patch(route('platform.settings.trial.update'), [
            'trial_plan_id' => $this->plan('professional')->id,
            'post_trial_plan_id' => $this->plan('essential')->id,
            'trial_days' => 14,
        ])->assertSessionHasNoErrors();

        $academy = Academy::factory()->create();
        $trial = AcademySubscriptionPeriod::query()->where('academy_id', $academy->id)->sole();
        $this->assertSame('professional', $trial->plan->code);
        $this->assertTrue($trial->ends_at->between(now()->addDays(14)->subMinute(), now()->addDays(14)->addMinute()));
    }

    public function test_the_platform_settlement_account_becomes_a_paystack_subaccount(): void
    {
        config(['services.paystack.secret_key' => 'sk_test_platform']);
        cache()->forget('paystack:banks:nigeria');
        Http::fake(function (ClientRequest $request) {
            $path = (string) parse_url($request->url(), PHP_URL_PATH);

            return match (true) {
                $path === '/bank' => Http::response(['status' => true, 'data' => [['code' => '058', 'name' => 'Guaranty Trust Bank']]]),
                $path === '/bank/resolve' => Http::response(['status' => true, 'data' => ['account_name' => 'BOOTCADEMY LTD']]),
                str_starts_with($path, '/subaccount') => Http::response(['status' => true, 'data' => ['subaccount_code' => 'ACCT_platform', 'active' => true]]),
                default => Http::response([], 404),
            };
        });
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('platform.settings.edit'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('settlement.status', 'none')->where('banks.0.code', '058'));
        $this->actingAs($admin)->patch(route('platform.settings.settlement-account.update'), [
            'bank_code' => '058', 'bank_account_number' => '012345678',
        ])->assertSessionHasErrors('bank_account_number');
        $this->actingAs($admin)->patch(route('platform.settings.settlement-account.update'), [
            'bank_code' => '058', 'bank_account_number' => '0123456789',
        ])->assertSessionHasNoErrors()->assertInertiaFlash('toast.message', 'Settlement account saved.');

        $settings = PlatformSetting::current();
        $this->assertSame('BOOTCADEMY LTD', $settings->bank_account_name);
        $this->assertSame('ACCT_platform', $settings->paystack_subaccount_code);
        $this->assertTrue($settings->hasActiveSubaccount());

        $this->actingAs($admin)->patch(route('platform.settings.settlement-account.update'), [
            'bank_code' => '', 'bank_account_number' => '',
        ])->assertSessionHasNoErrors();
        $this->assertFalse(PlatformSetting::current()->hasActiveSubaccount());
    }

    private function admin(): User
    {
        return User::factory()->create(['is_platform_super_admin' => true]);
    }

    private function plan(string $code): Plan
    {
        return Plan::query()->where('code', $code)->firstOrFail();
    }

    private function payment(Academy $academy, Plan $plan, string $status, int $amount, int $fee): SubscriptionPayment
    {
        return SubscriptionPayment::query()->create([
            'academy_id' => $academy->id, 'plan_id' => $plan->id, 'billing_interval' => BillingInterval::Monthly,
            'currency' => 'NGN', 'amount_minor' => $amount, 'surcharge_minor' => $fee, 'charged_amount_minor' => $amount + $fee,
            'reference' => 'SUB-TEST-'.$status.'-'.$academy->id, 'status' => $status,
            'verified_at' => $status === 'verified' ? now() : null,
        ]);
    }
}
