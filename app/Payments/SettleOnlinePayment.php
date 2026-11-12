<?php

namespace App\Payments;

use App\Enums\AcademyInvoiceStatus;
use App\Enums\AcademyPaymentMethod;
use App\Enums\AcademyPaymentStatus;
use App\Models\AcademyInvoice;
use App\Models\AcademyInvoiceInstallment;
use App\Models\AcademyPayment;
use App\Models\OnlinePaymentIntent;
use App\Notifications\AcademyPaymentReceiptNotification;
use App\Platform\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use RuntimeException;

class SettleOnlinePayment
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param array<string, mixed> $providerData */
    public function handle(OnlinePaymentIntent $intent, array $providerData, Request $request): OnlinePaymentIntent
    {
        if ($intent->status === 'verified') {
            return $intent;
        }
        $status = (string) ($providerData['status'] ?? '');
        if ($status !== 'success') {
            // "abandoned" only means the customer has not finished paying yet; it can still succeed.
            if (in_array($status, ['failed', 'reversed'], true)) {
                $intent->update(['status' => 'failed', 'failure_reason' => (string) ($providerData['gateway_response'] ?? $status)]);
            }

            return $intent->fresh();
        }
        if (($providerData['reference'] ?? null) !== $intent->reference
            || (int) ($providerData['amount'] ?? -1) !== $intent->charged_amount_minor
            || strtoupper((string) ($providerData['currency'] ?? '')) !== $intent->currency) {
            $intent->update(['status' => 'review_required', 'failure_reason' => 'Provider amount, currency or reference did not match the payment intent.']);
            throw new RuntimeException('The verified transaction did not match the expected payment.');
        }

        $payment = DB::transaction(function () use ($intent, $providerData, $request): AcademyPayment {
            $intent = OnlinePaymentIntent::query()->whereKey($intent->id)->lockForUpdate()->firstOrFail();
            if ($intent->status === 'verified' && $intent->academy_payment_id !== null) {
                return AcademyPayment::query()->findOrFail($intent->academy_payment_id);
            }
            do {
                $paymentReference = 'PAY-'.now()->format('Ym').'-'.Str::upper(Str::random(8));
            } while (AcademyPayment::query()->where('academy_id', $intent->academy_id)->where('reference', $paymentReference)->exists());

            $payment = AcademyPayment::query()->create([
                'academy_id' => $intent->academy_id, 'reference' => $paymentReference,
                'application_id' => $intent->application_id, 'player_id' => $intent->application->converted_player_id,
                'method' => AcademyPaymentMethod::Paystack, 'provider' => 'paystack', 'status' => AcademyPaymentStatus::Verified,
                'currency' => $intent->currency, 'amount_minor' => $intent->base_amount_minor,
                'gross_amount_minor' => $intent->charged_amount_minor, 'customer_fee_minor' => $intent->surcharge_minor,
                'provider_fee_minor' => isset($providerData['fees']) ? (int) $providerData['fees'] : null,
                'unallocated_minor' => 0, 'received_on' => now()->toDateString(),
                'external_reference' => $intent->reference, 'notes' => 'Verified online through Paystack.',
            ]);
            $unallocated = 0;
            $invoicePaid = [];
            foreach ($intent->allocation_plan as $planned) {
                $invoice = AcademyInvoice::query()->where('academy_id', $intent->academy_id)->whereKey($planned['invoice_id'])->lockForUpdate()->firstOrFail();
                $installment = AcademyInvoiceInstallment::query()->where('academy_id', $intent->academy_id)
                    ->where('academy_invoice_id', $invoice->id)->whereKey($planned['installment_id'])->lockForUpdate()->firstOrFail();
                $available = $invoice->status === AcademyInvoiceStatus::Void
                    ? 0
                    : max(0, $installment->amount_minor - $installment->paid_minor - $installment->written_off_minor);
                $allocated = min((int) $planned['amount_minor'], $available);
                if ($allocated > 0) {
                    $payment->allocations()->create([
                        'academy_id' => $intent->academy_id, 'academy_invoice_id' => $invoice->id,
                        'academy_invoice_installment_id' => $installment->id, 'amount_minor' => $allocated,
                    ]);
                    $installment->increment('paid_minor', $allocated);
                    $invoicePaid[$invoice->id] = ($invoicePaid[$invoice->id] ?? 0) + $allocated;
                }
                $unallocated += (int) $planned['amount_minor'] - $allocated;
            }
            foreach ($invoicePaid as $invoiceId => $paid) {
                $invoice = AcademyInvoice::query()->whereKey($invoiceId)->lockForUpdate()->firstOrFail();
                $invoice->paid_minor += $paid;
                $invoice->status = $invoice->balanceMinor() === 0
                    ? ($invoice->written_off_minor > 0 ? AcademyInvoiceStatus::WrittenOff : AcademyInvoiceStatus::Paid)
                    : AcademyInvoiceStatus::PartiallyPaid;
                $invoice->save();
            }
            $payment->update(['unallocated_minor' => $unallocated]);
            $intent->update([
                'status' => $unallocated > 0 ? 'review_required' : 'verified',
                'academy_payment_id' => $payment->id,
                'provider_fee_minor' => isset($providerData['fees']) ? (int) $providerData['fees'] : null,
                'verified_at' => now(),
                'failure_reason' => $unallocated > 0 ? 'Part of this verified payment requires manual allocation.' : null,
            ]);
            $this->audit->record('academy.payment.online-verified', null, $intent->academy, $payment, [
                'intent_id' => $intent->id, 'base_amount_minor' => $intent->base_amount_minor,
                'surcharge_minor' => $intent->surcharge_minor, 'charged_amount_minor' => $intent->charged_amount_minor,
                'provider_fee_minor' => $payment->provider_fee_minor, 'unallocated_minor' => $unallocated,
            ], $request);

            return $payment;
        });

        $freshIntent = $intent->fresh();
        if ($freshIntent->receipt_queued_at === null) {
            $email = $freshIntent->application->email ?? $freshIntent->application->guardian_email;
            if (is_string($email) && $email !== '') {
                Notification::route('mail', $email)->notify(new AcademyPaymentReceiptNotification($payment));
                $freshIntent->update(['receipt_queued_at' => now()]);
            }
        }

        return $freshIntent->fresh();
    }
}
