<?php

namespace App\Academies\Actions;

use App\Models\Academy;
use App\Models\AcademyInvoice;
use App\Models\User;
use App\Platform\AuditLogger;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateInvoiceInstallments
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param array<string, mixed> $data */
    public function handle(Academy $academy, AcademyInvoice $invoice, User $actor, array $data, Request $request): AcademyInvoice
    {
        abort_unless($invoice->academy_id === $academy->id, 404);

        return DB::transaction(function () use ($academy, $invoice, $actor, $data, $request): AcademyInvoice {
            $invoice = AcademyInvoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            if ($invoice->paid_minor !== 0 || $invoice->scholarship_concession_minor !== 0 || $invoice->sponsor_responsibility_minor !== 0) {
                throw ValidationException::withMessages(['installments' => 'Installments cannot be changed after payment or scholarship allocation.']);
            }
            $invoice->installments()->delete();
            foreach (array_values($data['installments']) as $index => $installment) {
                $invoice->installments()->create([
                    'academy_id' => $academy->id, 'name' => trim((string) $installment['name']),
                    'amount_minor' => Money::toMinor((string) $installment['amount']), 'paid_minor' => 0,
                    'due_on' => ($installment['due_on'] ?? null) ?: null,
                    'due_offset_days' => ($installment['due_offset_days'] ?? null) === null || ($installment['due_offset_days'] ?? '') === ''
                        ? null : (int) $installment['due_offset_days'],
                    'sequence' => $index + 1,
                ]);
            }
            $invoice->due_on = $invoice->installments()->min('due_on');
            $invoice->save();
            $this->audit->record('academy.invoice.installments_updated', $actor, $academy, $invoice, [
                'installment_count' => count($data['installments']), 'total_minor' => $invoice->total_minor,
            ], $request);

            return $invoice->load('installments');
        });
    }
}
