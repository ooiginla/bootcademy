<?php

namespace Tests\Feature\Platform;

use App\Enums\AcademyRole;
use App\Models\Academy;
use App\Models\AcademyMembership;
use App\Models\Application;
use App\Models\OnlinePaymentIntent;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Payments\PaystackFeeCalculator;
use App\Payments\RequeryPendingPayments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OnlinePaymentFeesTest extends TestCase
{
    use RefreshDatabase;

    private bool $resolves = true;

    private bool $acceptsSubaccount = true;

    private bool $faked = false;

    public function test_platform_admin_sets_paystack_and_platform_fees(): void
    {
        $admin = User::factory()->create(['is_platform_super_admin' => true]);

        $this->actingAs($admin)->get(route('platform.settings.edit'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('platform/settings')
                ->where('settings.paystack_percentage', '1.50')
                ->where('settings.paystack_cap', '2000.00')
                ->where('settings.platform_percentage', '0.00'));

        $this->actingAs($admin)->patch(route('platform.settings.update'), [
            'paystack_percentage' => '1.50', 'paystack_fixed_fee' => '100.00',
            'paystack_cap' => '2000.00', 'paystack_waiver_threshold' => '2500.00',
            'platform_percentage' => '1.00', 'platform_fixed_fee' => '50.00',
        ])->assertRedirect();

        $settings = PlatformSetting::current();
        $this->assertSame(100, $settings->platform_fee_basis_points);
        $this->assertSame(5000, $settings->platform_fee_fixed_minor);
        $this->assertDatabaseHas('audit_logs', ['action' => 'platform.settings.online-fees.updated', 'actor_user_id' => $admin->id]);
    }

    public function test_only_platform_admins_can_change_fees(): void
    {
        $user = User::factory()->create();
        $academy = Academy::factory()->create();

        $this->actingAs($user)->get(route('platform.settings.edit'))->assertForbidden();
        $this->actingAs($user)->patch(route('platform.settings.update'), [
            'paystack_percentage' => '9', 'paystack_fixed_fee' => '0', 'platform_percentage' => '9', 'platform_fixed_fee' => '0',
        ])->assertForbidden();
        $this->actingAs($user)->patch(route('platform.academies.paystack-fees.update', $academy), [
            'platform_percentage' => '9.00',
        ])->assertForbidden();
        $this->assertSame(0, PlatformSetting::current()->platform_fee_basis_points);
        $this->assertNull($academy->fresh()->platform_fee_basis_points);
    }

    public function test_surcharge_covers_paystack_and_platform_fee_so_the_academy_gets_its_full_amount(): void
    {
        PlatformSetting::current()->update(['platform_fee_basis_points' => 100, 'platform_fee_fixed_minor' => 5000]);
        $academy = Academy::factory()->create();
        $fees = app(PaystackFeeCalculator::class);

        // ₦100,000 fee: platform takes 1% + ₦50 = ₦1,050. Paystack's 1.5% + ₦100 is grossed up on ₦101,050.
        $breakdown = $fees->breakdown($academy, 10000000);
        $this->assertSame(105000, $breakdown['platform_fee']);
        $this->assertSame(164036, $breakdown['paystack_fee']);
        $this->assertSame(269036, $breakdown['surcharge']);
        // On a large fee Paystack's charge is capped at ₦2,000.
        $this->assertSame(200000, $fees->breakdown($academy, 50000000)['paystack_fee']);

        // ₦10,000 fee: after Paystack's actual charge on the gross, exactly the platform fee is left.
        $small = $fees->breakdown($academy, 1000000);
        $gross = 1000000 + $small['surcharge'];
        $paystackActual = (int) ceil($gross * 0.015) + 10000;
        $this->assertGreaterThanOrEqual($small['platform_fee'], $small['surcharge'] - $paystackActual);
        $this->assertLessThanOrEqual(1, $small['surcharge'] - $paystackActual - $small['platform_fee']);

        // An academy override replaces the default platform fee; clearing it returns to the default.
        $admin = User::factory()->create(['is_platform_super_admin' => true]);
        $this->actingAs($admin)->patch(route('platform.academies.paystack-fees.update', $academy), [
            'platform_percentage' => '0.50', 'platform_fixed_fee' => '',
        ])->assertRedirect();
        $academy->refresh();
        $this->assertSame(50, $academy->platform_fee_basis_points);
        $this->assertSame(50000, $fees->breakdown($academy, 10000000)['platform_fee']);
        $this->actingAs($admin)->patch(route('platform.academies.paystack-fees.update', $academy), [
            'platform_percentage' => '', 'platform_fixed_fee' => '',
        ])->assertRedirect();
        $this->assertNull($academy->fresh()->platform_fee_basis_points);
        $this->assertSame(105000, $fees->breakdown($academy->fresh(), 10000000)['platform_fee']);
    }

    public function test_saving_bank_details_confirms_the_account_and_creates_a_paystack_subaccount(): void
    {
        config(['services.paystack.secret_key' => 'sk_test_bank']);
        [$owner, $academy] = $this->owner();
        $this->fakePaystack();

        $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->get(route('academy.branding.edit'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('uses_paystack', true)
                ->where('banks.0.name', 'Access Bank')
                ->where('branding.settlement.status', 'none'));

        $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->patch(route('academy.branding.bank-details.update'), [
                'bank_code' => '058', 'bank_account_number' => '0123456789',
                // Typed names are ignored; the bank's record is used.
                'bank_account_name' => 'Someone Else',
            ])->assertSessionHasNoErrors()
            ->assertInertiaFlash('toast.message', 'Bank details updated.');

        $academy->refresh();
        $this->assertSame('Guaranty Trust Bank', $academy->bank_name);
        $this->assertSame('EXAMPLE ACADEMY LTD', $academy->bank_account_name);
        $this->assertSame('ACCT_new123', $academy->paystack_subaccount_code);
        $this->assertSame('active', $academy->paystack_subaccount_status);
        $this->assertTrue($academy->acceptsOnlinePayments());
        Http::assertSent(fn (ClientRequest $request): bool => $request->method() === 'POST'
            && str_ends_with($request->url(), '/subaccount')
            && $request['settlement_bank'] === '058'
            && $request['account_number'] === '0123456789'
            && $request['business_name'] === $academy->name);
        $this->assertDatabaseHas('audit_logs', ['academy_id' => $academy->id, 'action' => 'academy.paystack_subaccount.synced']);

        // Changing the account updates the same subaccount rather than creating another.
        $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->patch(route('academy.branding.bank-details.update'), [
                'bank_code' => '044', 'bank_account_number' => '0987654321',
            ])->assertSessionHasNoErrors();
        Http::assertSent(fn (ClientRequest $request): bool => $request->method() === 'PUT'
            && str_ends_with($request->url(), '/subaccount/ACCT_new123')
            && $request['settlement_bank'] === '044');
        $this->assertSame('ACCT_new123', $academy->fresh()->paystack_subaccount_code);

        // Removing the bank account switches online payments off.
        $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id])
            ->patch(route('academy.branding.bank-details.update'), ['bank_code' => '', 'bank_account_number' => ''])
            ->assertSessionHasNoErrors();
        $this->assertSame('inactive', $academy->fresh()->paystack_subaccount_status);
        $this->assertFalse($academy->fresh()->acceptsOnlinePayments());
    }

    public function test_unconfirmed_accounts_are_rejected_and_paystack_failures_keep_online_payments_off(): void
    {
        config(['services.paystack.secret_key' => 'sk_test_bank']);
        [$owner, $academy] = $this->owner();
        $this->fakePaystack(resolve: false);
        $member = $this->actingAs($owner)->withSession(['active_academy_id' => $academy->id]);

        $member->patch(route('academy.branding.bank-details.update'), ['bank_code' => '058', 'bank_account_number' => '0123456789'])
            ->assertSessionHasErrors('bank_account_number');
        $member->patch(route('academy.branding.bank-details.update'), ['bank_code' => '999', 'bank_account_number' => '0123456789'])
            ->assertSessionHasErrors('bank_code');
        $member->patch(route('academy.branding.bank-details.update'), ['bank_code' => '058', 'bank_account_number' => '12345'])
            ->assertSessionHasErrors('bank_account_number');
        $this->assertNull($academy->fresh()->bank_account_number);

        $this->fakePaystack(subaccount: false);
        $member->patch(route('academy.branding.bank-details.update'), ['bank_code' => '058', 'bank_account_number' => '0123456789'])
            ->assertSessionHasNoErrors();
        $academy->refresh();
        $this->assertSame('0123456789', $academy->bank_account_number);
        $this->assertSame('failed', $academy->paystack_subaccount_status);
        $this->assertStringContainsString('Account details are invalid', (string) $academy->paystack_subaccount_error);
        $this->assertFalse($academy->acceptsOnlinePayments());

        // The platform admin can send it again once Paystack is fixed.
        $this->fakePaystack();
        $admin = User::factory()->create(['is_platform_super_admin' => true]);
        $this->actingAs($admin)->get(route('platform.academies.branding.edit', $academy))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('managedAcademy.settlement.status', 'failed')
                ->where('managedAcademy.settlement.account_number_last4', '6789')
                ->where('managedAcademy.settlement.can_retry', true));
        $this->actingAs($admin)->post(route('platform.academies.paystack-subaccount.sync', $academy))->assertRedirect();
        $this->assertSame('active', $academy->fresh()->paystack_subaccount_status);

        // Another academy's owner cannot touch these bank details.
        [$otherOwner] = $this->owner();
        $this->actingAs($otherOwner)->post(route('platform.academies.paystack-subaccount.sync', $academy))->assertForbidden();
    }

    public function test_requery_confirms_payments_without_a_webhook_and_expires_abandoned_ones(): void
    {
        config(['services.paystack.secret_key' => 'sk_test_requery']);
        $academy = Academy::factory()->create();
        $paid = $this->intent($academy, 'BCM-PAID', now()->subMinutes(10));
        $open = $this->intent($academy, 'BCM-OPEN', now()->subMinutes(20));
        $stale = $this->intent($academy, 'BCM-STALE', now()->subHours(50));
        $recentlyChecked = $this->intent($academy, 'BCM-LATER', now()->subHours(3), now()->subMinutes(10));
        Http::fake(function (ClientRequest $request) use ($paid) {
            $reference = basename((string) parse_url($request->url(), PHP_URL_PATH));

            return Http::response(['status' => true, 'data' => $reference === 'BCM-PAID'
                ? ['status' => 'failed', 'gateway_response' => 'Declined', 'reference' => $paid->reference]
                : ['status' => 'abandoned', 'gateway_response' => 'The transaction was not completed', 'reference' => $reference]]);
        });

        $totals = app(RequeryPendingPayments::class)->handle();

        $this->assertSame(3, $totals['checked']);
        $this->assertSame('failed', $paid->fresh()->status);
        $this->assertSame('pending', $open->fresh()->status);
        $this->assertNotNull($open->fresh()->last_checked_at);
        $this->assertSame('expired', $stale->fresh()->status);
        $this->assertSame('pending', $recentlyChecked->fresh()->status);
        Http::assertNotSent(fn (ClientRequest $request): bool => str_contains($request->url(), 'BCM-LATER'));
    }

    public function test_online_payment_is_off_until_the_academy_has_an_active_subaccount(): void
    {
        config(['services.paystack.secret_key' => 'sk_test_off']);
        $academy = Academy::factory()->create();
        $this->assertFalse($academy->acceptsOnlinePayments());
        $academy->update(['paystack_subaccount_code' => 'ACCT_x', 'paystack_subaccount_status' => 'failed']);
        $this->assertFalse($academy->fresh()->acceptsOnlinePayments());
        $academy->update(['paystack_subaccount_status' => 'active']);
        $this->assertTrue($academy->fresh()->acceptsOnlinePayments());
        config(['services.paystack.secret_key' => null]);
        $this->assertFalse($academy->fresh()->acceptsOnlinePayments());
    }

    /** Http::fake stubs stack, so one fake is registered and later calls only change its answers. */
    private function fakePaystack(bool $resolve = true, bool $subaccount = true): void
    {
        $this->resolves = $resolve;
        $this->acceptsSubaccount = $subaccount;
        cache()->forget('paystack:banks:nigeria');
        if ($this->faked) {
            return;
        }
        $this->faked = true;
        Http::fake(function (ClientRequest $request) {
            $path = (string) parse_url($request->url(), PHP_URL_PATH);
            if ($path === '/bank') {
                return Http::response(['status' => true, 'data' => [
                    ['code' => '044', 'name' => 'Access Bank', 'active' => true],
                    ['code' => '058', 'name' => 'Guaranty Trust Bank', 'active' => true],
                ]]);
            }
            if ($path === '/bank/resolve') {
                return $this->resolves
                    ? Http::response(['status' => true, 'data' => ['account_name' => 'EXAMPLE ACADEMY LTD']])
                    : Http::response(['status' => false, 'message' => 'Could not resolve account name.'], 422);
            }
            if (str_starts_with($path, '/subaccount')) {
                return $this->acceptsSubaccount
                    ? Http::response(['status' => true, 'data' => ['subaccount_code' => 'ACCT_new123', 'active' => true]])
                    : Http::response(['status' => false, 'message' => 'Account details are invalid'], 400);
            }

            return Http::response([], 404);
        });
    }

    private function intent(Academy $academy, string $reference, mixed $createdAt, mixed $checkedAt = null): OnlinePaymentIntent
    {
        $intent = OnlinePaymentIntent::query()->create([
            'academy_id' => $academy->id,
            'application_id' => Application::query()->create([
                'academy_id' => $academy->id, 'reference' => 'APP-'.$reference, 'first_name' => 'Ada', 'last_name' => 'Okafor',
            ])->id,
            'provider' => 'paystack', 'reference' => $reference, 'selection_key' => 'initial',
            'status' => 'pending', 'currency' => 'NGN', 'base_amount_minor' => 100000,
            'surcharge_minor' => 1600, 'charged_amount_minor' => 101600, 'allocation_plan' => [],
            'access_code' => 'code-'.$reference, 'last_checked_at' => $checkedAt,
        ]);
        $intent->forceFill(['created_at' => $createdAt])->save();

        return $intent;
    }

    /** @return array{User, Academy} */
    private function owner(): array
    {
        $owner = User::factory()->create();
        $academy = Academy::factory()->for($owner, 'creator')->create();
        AcademyMembership::factory()->for($owner)->for($academy)->asRole(AcademyRole::OwnerAdmin)->create();

        return [$owner, $academy];
    }
}
