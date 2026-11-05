<?php

namespace App\Academies\Actions;

use App\Enums\AcademyInvoiceStatus;
use App\Models\Academy;
use App\Models\AcademyExpense;
use App\Models\AcademyInvoice;
use App\Models\AcademyInvoiceWriteOff;
use App\Models\User;
use App\Platform\AuditLogger;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WriteOffAcademyInvoice
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param array<string, mixed> $data */
    public function handle(Academy $academy, AcademyInvoice $invoice, User $actor, array $data, Request $request): AcademyInvoiceWriteOff
    {
        return DB::transaction(function () use ($academy, $invoice, $actor, $data, $request): AcademyInvoiceWriteOff {
            $invoice = AcademyInvoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            abort_unless($invoice->academy_id === $academy->id, 404);
            if (in_array($invoice->status, [AcademyInvoiceStatus::Void, AcademyInvoiceStatus::Paid, AcademyInvoiceStatus::WrittenOff], true)) {
                throw ValidationException::withMessages(['amount' => 'This invoice cannot be written off.']);
            }
            $amount = Money::toMinor((string) $data['amount']);
            if ($amount > $invoice->balanceMinor()) {
                throw ValidationException::withMessages(['amount' => 'The write-off cannot exceed the outstanding balance.']);
            }
            $remaining = $amount;
            foreach ($invoice->installments()->orderBy('sequence')->lockForUpdate()->get() as $installment) {
                $available = $installment->amount_minor - $installment->paid_minor - $installment->written_off_minor;
                $allocated = min($remaining, $available);
                if ($allocated > 0) {
                    $installment->increment('written_off_minor', $allocated);
                    $remaining -= $allocated;
                }
                if ($remaining === 0) {
                    break;
                }
            }
            if ($remaining !== 0) {
                throw ValidationException::withMessages(['amount' => 'The write-off could not be allocated to this invoice.']);
            }
            $writeOff = AcademyInvoiceWriteOff::query()->create([
                'academy_id' => $academy->id, 'academy_invoice_id' => $invoice->id,
                'amount_minor' => $amount, 'reason' => trim((string) $data['reason']), 'created_by_user_id' => $actor->id,
            ]);
            $invoice->written_off_minor += $amount;
            $invoice->status = $invoice->balanceMinor() === 0
                ? AcademyInvoiceStatus::WrittenOff
                : ($invoice->paid_minor > 0 ? AcademyInvoiceStatus::PartiallyPaid : AcademyInvoiceStatus::PartiallyWrittenOff);
            $invoice->save();
            AcademyExpense::query()->create([
                'academy_id' => $academy->id, 'academy_cycle_id' => $invoice->academy_cycle_id,
                'academy_invoice_write_off_id' => $writeOff->id, 'category' => 'invoice_write_off',
                'description' => "Write-off {$invoice->number}: ".trim((string) $data['reason']),
                'currency' => $invoice->currency, 'amount_minor' => $amount, 'incurred_on' => today(),
                'created_by_user_id' => $actor->id,
            ]);
            $this->audit->record('academy.invoice.written_off', $actor, $academy, $invoice, [
                'write_off_id' => $writeOff->id, 'amount_minor' => $amount, 'reason' => trim((string) $data['reason']),
            ], $request);

            return $writeOff;
        });
    }
}
