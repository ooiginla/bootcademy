<?php

namespace App\Platform\Subscriptions;

use App\Enums\BillingInterval;
use App\Models\Academy;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Payments\Contracts\PaymentProvider;
use App\Payments\PaystackFeeCalculator;
use App\Platform\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Opens a Paystack payment for a subscription period.
 *
 * The academy pays the plan price plus the online payment fee. When the platform's settlement
 * subaccount is active, the plan price settles there and the fee stays in the main Paystack
 * account, which pays Paystack's charge from it. Without a subaccount the whole payment stays in
 * the main account.
 */
class InitializeSubscriptionPayment
{
    public function __construct(
        private readonly PaymentProvider $provider,
        private readonly PaystackFeeCalculator $fees,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(Academy $academy, Plan $plan, BillingInterval $interval, User $actor, Request $request): SubscriptionPayment
    {
        if (! filled(config('services.paystack.secret_key'))) {
            throw ValidationException::withMessages(['plan_id' => 'Online payment is not available right now. Please contact support.']);
        }
        if (! $plan->is_active) {
            throw ValidationException::withMessages(['plan_id' => 'That plan is no longer available.']);
        }
        $amount = $plan->priceFor($interval, $academy);
        if ($amount <= 0) {
            throw ValidationException::withMessages(['plan_id' => 'That plan has no price for this billing period. Please contact support.']);
        }

        $breakdown = $this->fees->subscriptionBreakdown($amount);
        $settings = PlatformSetting::current();
        $subaccount = $settings->hasActiveSubaccount() ? $settings->paystack_subaccount_code : null;

        $payment = SubscriptionPayment::query()->create([
            'academy_id' => $academy->id,
            'plan_id' => $plan->id,
            'billing_interval' => $interval,
            'currency' => $plan->currency,
            'amount_minor' => $amount,
            'surcharge_minor' => $breakdown['surcharge'],
            'platform_fee_minor' => $breakdown['platform_fee'],
            'paystack_fee_estimate_minor' => $breakdown['paystack_fee'],
            'charged_amount_minor' => $amount + $breakdown['surcharge'],
            'provider' => 'paystack',
            'reference' => 'SUB-'.now()->format('Ymd').'-'.Str::upper(Str::random(10)),
            'subaccount_code' => $subaccount,
            'status' => 'pending',
            'initiated_by_user_id' => $actor->id,
        ]);

        try {
            $result = $this->provider->initialize(
                $actor->email,
                $payment->charged_amount_minor,
                $payment->currency,
                $payment->reference,
                [
                    'purpose' => 'academy_subscription',
                    'subscription_payment_id' => $payment->id,
                    'academy_id' => $academy->id,
                    'plan' => $plan->code,
                    'billing_interval' => $interval->value,
                ],
                $subaccount === null ? null : ['subaccount' => $subaccount, 'transaction_charge' => $breakdown['surcharge']],
            );
        } catch (RuntimeException $exception) {
            $payment->update(['status' => 'failed', 'failure_reason' => mb_substr($exception->getMessage(), 0, 500)]);
            throw ValidationException::withMessages(['plan_id' => 'Paystack could not start the payment. Please try again.']);
        }

        $payment->update(['access_code' => $result['access_code']]);
        $this->audit->record('academy.subscription.payment-initialized', $actor, $academy, $payment, [
            'plan_id' => $plan->id, 'billing_interval' => $interval->value,
            'amount_minor' => $amount, 'charged_amount_minor' => $payment->charged_amount_minor,
        ], $request);

        return $payment;
    }
}
