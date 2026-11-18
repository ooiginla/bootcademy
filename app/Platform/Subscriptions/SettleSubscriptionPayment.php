<?php

namespace App\Platform\Subscriptions;

use App\Models\SubscriptionPayment;
use App\Platform\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Records the result of a verified Paystack transaction for a subscription payment.
 *
 * Safe to call more than once for the same payment: a verified payment adds exactly one period.
 */
class SettleSubscriptionPayment
{
    public function __construct(
        private readonly AddSubscriptionPeriod $addPeriod,
        private readonly AuditLogger $audit,
    ) {}

    /** @param array<string, mixed> $providerData */
    public function handle(SubscriptionPayment $payment, array $providerData, Request $request): SubscriptionPayment
    {
        if ($payment->status === 'verified') {
            return $payment;
        }
        $status = (string) ($providerData['status'] ?? '');
        if ($status !== 'success') {
            // "abandoned" only means the payer has not finished yet; it can still succeed.
            if (in_array($status, ['failed', 'reversed'], true)) {
                $payment->update(['status' => 'failed', 'failure_reason' => mb_substr((string) ($providerData['gateway_response'] ?? $status), 0, 500)]);
            }

            return $payment->fresh() ?? $payment;
        }

        $paidSubaccount = data_get($providerData, 'subaccount.subaccount_code');
        $subaccountMismatch = $payment->subaccount_code !== null && is_string($paidSubaccount) && $paidSubaccount !== ''
            && $paidSubaccount !== $payment->subaccount_code;
        if ($subaccountMismatch
            || ($providerData['reference'] ?? null) !== $payment->reference
            || (int) ($providerData['amount'] ?? -1) !== $payment->charged_amount_minor
            || strtoupper((string) ($providerData['currency'] ?? '')) !== $payment->currency) {
            $payment->update(['status' => 'review_required', 'failure_reason' => 'Provider amount, currency, reference or subaccount did not match the subscription payment.']);
            throw new RuntimeException('The verified transaction did not match the expected subscription payment.');
        }

        return DB::transaction(function () use ($payment, $providerData, $request): SubscriptionPayment {
            $payment = SubscriptionPayment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($payment->status === 'verified') {
                return $payment;
            }
            $period = $this->addPeriod->handle(
                $payment->academy,
                $payment->plan,
                'paid',
                $payment->billing_interval,
                $payment,
                null,
                $request,
            );
            $payment->update([
                'status' => 'verified',
                'verified_at' => now(),
                'failure_reason' => null,
                'provider_fee_minor' => isset($providerData['fees']) ? (int) $providerData['fees'] : null,
            ]);
            $this->audit->record('academy.subscription.payment-verified', null, $payment->academy, $payment, [
                'period_id' => $period->id,
                'charged_amount_minor' => $payment->charged_amount_minor,
                'provider_fee_minor' => $payment->provider_fee_minor,
            ], $request);

            return $payment;
        });
    }
}
