<?php

namespace Tests\Feature\Academies;

use App\Academies\Actions\CreateAcademy;
use App\Enums\AcademyRole;
use App\Enums\BillingInterval;
use App\Models\Academy;
use App\Models\AcademyMembership;
use App\Models\AcademyPlanPrice;
use App\Models\AcademySubscriptionPeriod;
use App\Models\Branch;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Payments\RequeryPendingPayments;
use App\Platform\AcademyEntitlements;
use App\Platform\Subscriptions\AddSubscriptionPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AcademySubscriptionTest extends TestCase
{
    use RefreshDatabase;

    /** Essential monthly: ₦25,000 plus Paystack's 1.5% + ₦100 grossed up = ₦482.24. */
    private const int ESSENTIAL_MONTHLY_FEE = 48224;

    /** @var array<string, mixed> what Paystack's verify endpoint answers */
    private array $verifyAnswer = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.paystack.secret_key' => 'sk_test_subscriptions']);
        Http::fake(function (ClientRequest $request) {
            $path = (string) parse_url($request->url(), PHP_URL_PATH);
            if ($path === '/transaction/initialize') {
                return Http::response(['status' => true, 'data' => [
                    'access_code' => 'AC_'.$request['reference'], 'reference' => $request['reference'],
                ]]);
            }
            if (str_starts_with($path, '/transaction/verify/')) {
                return Http::response(['status' => true, 'data' => $this->verifyAnswer]);
            }

            return Http::response([], 404);
        });
    }

    public function test_a_new_academy_starts_a_thirty_day_enterprise_trial(): void
    {
        $owner = User::factory()->create();
        $academy = app(CreateAcademy::class)->handle($owner, [
            'name' => 'Lekki Stars', 'academy_type' => 'football', 'country_code' => 'NG',
            'timezone' => 'Africa/Lagos', 'currency' => 'NGN',
        ]);

        $period = AcademySubscriptionPeriod::query()->where('academy_id', $academy->id)->sole();
        $this->assertSame('trial', $period->kind);
        $this->assertSame('enterprise', $period->plan->code);
        $this->assertTrue($period->ends_at->between(now()->addDays(30)->subMinute(), now()->addDays(30)->addMinute()));
        $entitlements = AcademyEntitlements::for($academy);
        $this->assertSame('trialing', $entitlements->state());
        $this->assertTrue($entitlements->allows('api_access'));
    }

    public function test_a_trial_academy_uses_the_whole_app_and_sees_the_three_plans(): void
    {
        [$owner, $academy] = $this->owner();

        $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->get(route('dashboard'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('academy.subscription.has_access', true)
                ->where('academy.subscription.state', 'trialing'));

        $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->get(route('academy.subscription.show'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('academies/subscription')
                ->where('state', 'trialing')
                ->where('current.plan_name', 'Enterprise')
                ->has('plans', 3)
                ->where('plans.0.name', 'Essential')
                ->where('plans.0.prices.monthly.amount_minor', 2_500_000)
                ->where('plans.0.prices.monthly.fee_minor', self::ESSENTIAL_MONTHLY_FEE)
                ->where('plans.0.prices.quarterly.amount_minor', 7_125_000)
                ->where('plans.0.branch_limit', 1)
                ->where('plans.0.flags.api_access', false)
                ->where('plans.1.is_recommended', true)
                ->where('plans.2.flags.api_access', true)
                ->where('plans.2.flags.custom_domain', true));
    }

    public function test_a_lapsed_academy_only_reaches_the_subscription_page(): void
    {
        [$owner, $academy] = $this->owner();
        $this->expireTrial($academy);
        $session = ['active_academy_id' => $academy->id];

        $this->actingAs($owner)->withSession($session)->get(route('dashboard'))
            ->assertRedirect(route('academy.subscription.show'));
        $this->actingAs($owner)->withSession($session)->post(route('academy.branches.store'), [
            'name' => 'Ikeja', 'timezone' => 'Africa/Lagos', 'is_active' => true,
        ])->assertRedirect(route('academy.subscription.show'));
        $this->assertSame(0, Branch::query()->where('academy_id', $academy->id)->count());

        $this->actingAs($owner)->withSession($session)->get(route('academy.subscription.show'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('state', 'lapsed')
                ->where('plan_name', 'Essential')
                ->where('current', null)
                ->where('academy.subscription.has_access', false)
                ->where('can_pay', true)
                ->has('plans', 3));
    }

    public function test_staff_who_cannot_pay_see_a_notice_and_cannot_start_a_payment(): void
    {
        $academy = Academy::factory()->create();
        $coach = User::factory()->create();
        AcademyMembership::factory()->for($coach)->for($academy)->asRole(AcademyRole::Coach)->create();
        $this->expireTrial($academy);
        $session = ['active_academy_id' => $academy->id];

        $this->actingAs($coach)->withSession($session)->get(route('dashboard'))
            ->assertRedirect(route('academy.subscription.show'));
        $this->actingAs($coach)->withSession($session)->get(route('academy.subscription.show'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('can_pay', false)->has('plans', 0)->has('payments', 0));
        $this->actingAs($coach)->withSession($session)->postJson(route('academy.subscription.payments.store'), [
            'plan_id' => $this->plan('essential')->id, 'billing_interval' => 'monthly',
        ])->assertForbidden();
        $this->assertSame(0, SubscriptionPayment::query()->count());
    }

    public function test_paying_with_paystack_settles_to_the_platform_subaccount_and_ends_the_trial(): void
    {
        PlatformSetting::current()->update(['paystack_subaccount_code' => 'ACCT_platform', 'paystack_subaccount_status' => 'active']);
        [$owner, $academy] = $this->owner();
        $session = ['active_academy_id' => $academy->id];

        $response = $this->actingAs($owner)->withSession($session)->postJson(route('academy.subscription.payments.store'), [
            'plan_id' => $this->plan('essential')->id, 'billing_interval' => 'monthly',
        ])->assertOk()->assertJsonStructure(['access_code', 'reference', 'verify_url']);

        $payment = SubscriptionPayment::query()->sole();
        $this->assertSame(2_500_000, $payment->amount_minor);
        $this->assertSame(self::ESSENTIAL_MONTHLY_FEE, $payment->surcharge_minor);
        $this->assertSame(2_500_000 + self::ESSENTIAL_MONTHLY_FEE, $payment->charged_amount_minor);
        Http::assertSent(fn (ClientRequest $request) => str_ends_with($request->url(), '/transaction/initialize')
            && $request['amount'] === 2_500_000 + self::ESSENTIAL_MONTHLY_FEE
            && $request['subaccount'] === 'ACCT_platform'
            && $request['transaction_charge'] === self::ESSENTIAL_MONTHLY_FEE
            && $request['bearer'] === 'account'
            && $request['email'] === $owner->email);

        $this->verifyAnswer = $this->success($payment, 'ACCT_platform');
        $this->actingAs($owner)->withSession($session)->postJson($response->json('verify_url'))
            ->assertOk()->assertJson(['status' => 'verified']);
        // Verifying again is harmless.
        $this->actingAs($owner)->withSession($session)->postJson($response->json('verify_url'))
            ->assertOk()->assertJson(['status' => 'verified']);

        $periods = AcademySubscriptionPeriod::query()->where('academy_id', $academy->id)->get()->keyBy('kind');
        $this->assertCount(2, $periods);
        $this->assertTrue($periods['trial']->ends_at->lte(now()));
        $this->assertSame($payment->id, $periods['paid']->subscription_payment_id);
        $this->assertTrue($periods['paid']->ends_at->between(now()->addMonthNoOverflow()->subMinute(), now()->addMonthNoOverflow()->addMinute()));
        $entitlements = AcademyEntitlements::for($academy);
        $this->assertSame('active', $entitlements->state());
        $this->assertSame('essential', $entitlements->plan()?->code);
        $this->assertDatabaseHas('audit_logs', ['action' => 'academy.subscription.payment-verified']);

        $this->actingAs($owner)->withSession($session)->get(route('academy.subscription.show'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('state', 'active')
                ->where('current.kind', 'paid')
                ->where('current.billing_interval', 'monthly')
                ->where('payments.0.status', 'verified')
                ->where('usage.branches', 0)
                ->where('limits.branches', 1));
    }

    public function test_renewing_early_starts_the_new_period_when_paid_time_runs_out(): void
    {
        [$owner, $academy] = $this->owner();
        $paidUntil = now()->addDays(10)->startOfSecond();
        app(AddSubscriptionPeriod::class)->handle($academy, $this->plan('essential'), 'granted', null, null, null, Request::create('/'), $paidUntil);
        $session = ['active_academy_id' => $academy->id];

        $response = $this->actingAs($owner)->withSession($session)->postJson(route('academy.subscription.payments.store'), [
            'plan_id' => $this->plan('professional')->id, 'billing_interval' => 'annual',
        ])->assertOk();
        $payment = SubscriptionPayment::query()->sole();
        $this->assertSame(54_000_000, $payment->amount_minor);
        Http::assertSent(fn (ClientRequest $request) => str_ends_with($request->url(), '/transaction/initialize')
            && ! isset($request['subaccount']));

        $this->verifyAnswer = $this->success($payment);
        $this->actingAs($owner)->withSession($session)->postJson($response->json('verify_url'))->assertOk();

        $paid = AcademySubscriptionPeriod::query()->where('kind', 'paid')->sole();
        $this->assertTrue($paid->starts_at->equalTo($paidUntil));
        $this->assertTrue($paid->ends_at->equalTo($paidUntil->addMonthsNoOverflow(12)));
        $entitlements = AcademyEntitlements::for($academy);
        $this->assertSame('essential', $entitlements->plan()?->code);
        $this->assertTrue($entitlements->accessEndsAt()?->equalTo($paid->ends_at));
    }

    public function test_a_platform_price_for_one_academy_replaces_the_list_price(): void
    {
        [$owner, $academy] = $this->owner();
        $essential = $this->plan('essential');
        AcademyPlanPrice::query()->create([
            'academy_id' => $academy->id, 'plan_id' => $essential->id,
            'monthly_price_minor' => 1_000_000, 'quarterly_price_minor' => 2_800_000, 'annual_price_minor' => 10_000_000,
        ]);
        $session = ['active_academy_id' => $academy->id];

        $this->actingAs($owner)->withSession($session)->get(route('academy.subscription.show'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('plans.0.has_custom_price', true)
                ->where('plans.0.prices.annual.amount_minor', 10_000_000)
                ->where('plans.0.prices.annual.list_amount_minor', 27_000_000)
                ->where('plans.1.has_custom_price', false));
        $this->actingAs($owner)->withSession($session)->postJson(route('academy.subscription.payments.store'), [
            'plan_id' => $essential->id, 'billing_interval' => 'annual',
        ])->assertOk();
        $this->assertSame(10_000_000, SubscriptionPayment::query()->sole()->amount_minor);

        // Another academy still pays the list price.
        [$otherOwner, $other] = $this->owner();
        $this->actingAs($otherOwner)->withSession(['active_academy_id' => $other->id])
            ->postJson(route('academy.subscription.payments.store'), ['plan_id' => $essential->id, 'billing_interval' => 'annual'])
            ->assertOk();
        $this->assertSame(27_000_000, SubscriptionPayment::query()->where('academy_id', $other->id)->sole()->amount_minor);
    }

    public function test_a_payment_that_does_not_match_is_held_for_review_and_grants_nothing(): void
    {
        [$owner, $academy] = $this->owner();
        $session = ['active_academy_id' => $academy->id];
        $response = $this->actingAs($owner)->withSession($session)->postJson(route('academy.subscription.payments.store'), [
            'plan_id' => $this->plan('enterprise')->id, 'billing_interval' => 'monthly',
        ])->assertOk();
        $payment = SubscriptionPayment::query()->sole();

        $this->verifyAnswer = [...$this->success($payment), 'amount' => 100];
        $this->actingAs($owner)->withSession($session)->postJson($response->json('verify_url'))->assertStatus(422);

        $this->assertSame('review_required', $payment->fresh()?->status);
        $this->assertSame(0, AcademySubscriptionPeriod::query()->where('kind', 'paid')->count());
    }

    public function test_an_academy_cannot_verify_or_see_another_academys_payment(): void
    {
        [$owner, $academy] = $this->owner();
        [$otherOwner, $other] = $this->owner();
        $this->actingAs($otherOwner)->withSession(['active_academy_id' => $other->id])
            ->postJson(route('academy.subscription.payments.store'), ['plan_id' => $this->plan('essential')->id, 'billing_interval' => 'monthly'])
            ->assertOk();
        $payment = SubscriptionPayment::query()->sole();
        $this->verifyAnswer = $this->success($payment);

        $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->postJson(route('academy.subscription.payments.verify', $payment))->assertNotFound();
        $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->get(route('academy.subscription.show'))->assertInertia(fn (Assert $page) => $page->has('payments', 0));
        $this->assertSame('pending', $payment->fresh()?->status);
    }

    public function test_the_requery_job_confirms_subscription_payments_without_the_browser(): void
    {
        [$owner, $academy] = $this->owner();
        $this->expireTrial($academy);
        $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->postJson(route('academy.subscription.payments.store'), ['plan_id' => $this->plan('essential')->id, 'billing_interval' => 'quarterly'])
            ->assertOk();
        $payment = SubscriptionPayment::query()->sole();
        $this->assertSame(BillingInterval::Quarterly, $payment->billing_interval);

        $this->verifyAnswer = $this->success($payment);
        $totals = app(RequeryPendingPayments::class)->handle();

        $this->assertSame(1, $totals['verified']);
        $this->assertSame('verified', $payment->fresh()?->status);
        $this->assertTrue(AcademyEntitlements::for($academy)->hasAccess());
    }

    public function test_payment_cannot_start_without_paystack_or_for_an_inactive_plan(): void
    {
        [$owner, $academy] = $this->owner();
        $session = ['active_academy_id' => $academy->id];
        $inactive = $this->plan('professional');
        $inactive->update(['is_active' => false]);

        $this->actingAs($owner)->withSession($session)->postJson(route('academy.subscription.payments.store'), [
            'plan_id' => $inactive->id, 'billing_interval' => 'monthly',
        ])->assertJsonValidationErrors('plan_id');
        $this->actingAs($owner)->withSession($session)->postJson(route('academy.subscription.payments.store'), [
            'plan_id' => $this->plan('essential')->id, 'billing_interval' => 'weekly',
        ])->assertJsonValidationErrors('billing_interval');

        config(['services.paystack.secret_key' => null]);
        $this->actingAs($owner)->withSession($session)->postJson(route('academy.subscription.payments.store'), [
            'plan_id' => $this->plan('essential')->id, 'billing_interval' => 'monthly',
        ])->assertJsonValidationErrors('plan_id');
        $this->assertSame(0, SubscriptionPayment::query()->count());
    }

    /** @return array{User, Academy} */
    private function owner(): array
    {
        $owner = User::factory()->create();
        $academy = Academy::factory()->for($owner, 'creator')->create();
        AcademyMembership::factory()->for($owner)->for($academy)->create();

        return [$owner, $academy];
    }

    private function expireTrial(Academy $academy): void
    {
        AcademySubscriptionPeriod::query()->where('academy_id', $academy->id)
            ->update(['starts_at' => now()->subDays(40), 'ends_at' => now()->subDays(10)]);
    }

    private function plan(string $code): Plan
    {
        return Plan::query()->where('code', $code)->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function success(SubscriptionPayment $payment, ?string $subaccount = null): array
    {
        return [
            'status' => 'success', 'reference' => $payment->reference, 'amount' => $payment->charged_amount_minor,
            'currency' => 'NGN', 'fees' => 38000,
            'subaccount' => $subaccount === null ? [] : ['subaccount_code' => $subaccount],
        ];
    }
}
