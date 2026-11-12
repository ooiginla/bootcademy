<?php

namespace App\Commerce;

use App\Models\StoreOrder;
use App\Models\StoreOrderPayment;
use App\Notifications\StoreOrderPaymentReceiptNotification;
use App\Platform\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use RuntimeException;

class SettleStoreOrderPayment
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param array<string, mixed> $providerData */
    public function handle(StoreOrderPayment $payment, array $providerData, Request $request): StoreOrderPayment
    {
        if ($payment->status === 'verified') {
            return $payment;
        }

        $status = (string) ($providerData['status'] ?? '');
        if ($status !== 'success') {
            // "abandoned" only means the customer has not finished paying yet; it can still succeed.
            if (in_array($status, ['failed', 'reversed'], true)) {
                $payment->update([
                    'status' => 'failed',
                    'failure_reason' => (string) ($providerData['gateway_response'] ?? $status),
                ]);
            }

            return $payment->fresh();
        }

        if (($providerData['reference'] ?? null) !== $payment->reference
            || (int) ($providerData['amount'] ?? -1) !== $payment->charged_amount_minor
            || strtoupper((string) ($providerData['currency'] ?? '')) !== $payment->currency) {
            $payment->update([
                'status' => 'review_required',
                'failure_reason' => 'Provider amount, currency or reference did not match the store payment.',
            ]);
            throw new RuntimeException('The verified transaction did not match the expected store payment.');
        }

        $payment = DB::transaction(function () use ($payment, $providerData): StoreOrderPayment {
            $payment = StoreOrderPayment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($payment->status === 'verified') {
                return $payment;
            }
            $order = StoreOrder::query()->where('academy_id', $payment->academy_id)
                ->whereKey($payment->store_order_id)->lockForUpdate()->firstOrFail();
            $alreadyPaid = (int) $order->payments()->where('status', 'verified')->sum('amount_minor');
            if ($payment->amount_minor > max(0, $order->total_minor - $alreadyPaid)) {
                $payment->update([
                    'status' => 'review_required',
                    'failure_reason' => 'The verified amount exceeds the outstanding order balance.',
                ]);

                return $payment->fresh(['order', 'academy']);
            }

            $payment->update([
                'status' => 'verified',
                'external_reference' => $payment->reference,
                'provider_fee_minor' => isset($providerData['fees']) ? (int) $providerData['fees'] : null,
                'verified_at' => now(),
                'failure_reason' => null,
            ]);
            $paid = $alreadyPaid + $payment->amount_minor;
            $order->update([
                'payment_status' => $paid >= $order->total_minor ? 'paid' : 'partially_paid',
                'paid_at' => $paid >= $order->total_minor ? now() : null,
            ]);

            return $payment->fresh(['order', 'academy']);
        }, 3);

        if ($payment->status !== 'verified') {
            return $payment;
        }

        $this->audit->record('academy.store.payment.online-verified', null, $payment->academy, $payment, [
            'order_id' => $payment->store_order_id,
            'base_amount_minor' => $payment->amount_minor,
            'surcharge_minor' => $payment->surcharge_minor,
            'charged_amount_minor' => $payment->charged_amount_minor,
            'provider_fee_minor' => $payment->provider_fee_minor,
        ], $request);

        if ($payment->receipt_queued_at === null && filled($payment->order->customer_email)) {
            Notification::route('mail', $payment->order->customer_email)
                ->notify(new StoreOrderPaymentReceiptNotification($payment));
            $payment->update(['receipt_queued_at' => now()]);
        }

        return $payment->fresh(['order']);
    }
}
