<?php

namespace App\Academies\Actions;

use App\Enums\AcademyInvoiceStatus;
use App\Enums\AcademyPaymentStatus;
use App\Models\Academy;
use App\Models\AcademyInvoice;
use App\Models\AcademyInvoiceInstallment;
use App\Models\AcademyPayment;
use App\Models\AcademyPaymentAdjustment;
use App\Models\AcademyPaymentAllocation;
use App\Models\User;
use App\Platform\AuditLogger;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReverseAcademyPayment
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param array<string, mixed> $data */
    public function handle(Academy $academy, AcademyPayment $payment, User $actor, array $data, Request $request): AcademyPaymentAdjustment
    {
        abort_unless($payment->academy_id === $academy->id, 404);

        return DB::transaction(function () use ($academy, $payment, $actor, $data, $request): AcademyPaymentAdjustment {
            $payment = AcademyPayment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $amount = Money::toMinor((string) $data['amount']);
            $available = $payment->amount_minor - $payment->reversed_minor;
            if ($amount > $available) {
                throw ValidationException::withMessages(['amount' => 'The reversal cannot exceed the unreversed payment amount.']);
            }

            $adjustment = AcademyPaymentAdjustment::query()->create([
                'academy_id' => $academy->id, 'academy_payment_id' => $payment->id,
                'type' => 'reversal', 'amount_minor' => $amount, 'reason' => $data['reason'],
                'effective_on' => $data['effective_on'], 'created_by_user_id' => $actor->id,
            ]);
            $remaining = $amount;
            $invoiceIds = [];
            $allocations = $payment->allocations()->withSum('adjustmentAllocations as reversed_minor', 'amount_minor')
                ->orderByDesc('id')->lockForUpdate()->get();
            foreach ($allocations as $allocation) {
                /** @var AcademyPaymentAllocation $allocation */
                $reversible = $allocation->amount_minor - (int) $allocation->reversed_minor;
                $reversed = min($remaining, $reversible);
                if ($reversed === 0) {
                    continue;
                }
                $adjustment->allocations()->create([
                    'academy_id' => $academy->id, 'academy_payment_allocation_id' => $allocation->id,
                    'amount_minor' => $reversed,
                ]);
                if ($allocation->academy_invoice_installment_id) {
                    $installment = AcademyInvoiceInstallment::query()->where('academy_id', $academy->id)
                        ->whereKey($allocation->academy_invoice_installment_id)->lockForUpdate()->firstOrFail();
                    $installment->paid_minor -= $reversed;
                    $installment->save();
                }
                $invoice = AcademyInvoice::query()->where('academy_id', $academy->id)
                    ->whereKey($allocation->academy_invoice_id)->lockForUpdate()->firstOrFail();
                $invoice->paid_minor -= $reversed;
                $invoice->save();
                $invoiceIds[] = $allocation->academy_invoice_id;
                $remaining -= $reversed;
                if ($remaining === 0) {
                    break;
                }
            }
            if ($remaining !== 0) {
                throw ValidationException::withMessages(['amount' => 'The reversal could not be allocated to the original invoices.']);
            }
            foreach (array_unique($invoiceIds) as $invoiceId) {
                $invoice = AcademyInvoice::query()->where('academy_id', $academy->id)->whereKey($invoiceId)->lockForUpdate()->firstOrFail();
                $invoice->status = match (true) {
                    $invoice->balanceMinor() === 0 && $invoice->written_off_minor > 0 => AcademyInvoiceStatus::WrittenOff,
                    $invoice->balanceMinor() === 0 => AcademyInvoiceStatus::Paid,
                    $invoice->paid_minor > 0 => AcademyInvoiceStatus::PartiallyPaid,
                    $invoice->written_off_minor > 0 => AcademyInvoiceStatus::PartiallyWrittenOff,
                    default => AcademyInvoiceStatus::Issued,
                };
                $invoice->save();
            }
            $payment->reversed_minor += $amount;
            if ($payment->reversed_minor === $payment->amount_minor) {
                $payment->status = AcademyPaymentStatus::Reversed;
            }
            $payment->save();
            $this->audit->record('academy.payment.reversed', $actor, $academy, $payment, [
                'adjustment_id' => $adjustment->id, 'amount_minor' => $amount, 'reason' => $data['reason'],
            ], $request);

            return $adjustment;
        });
    }
}
