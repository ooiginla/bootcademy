<?php

namespace App\Payments;

use App\Enums\AcademyInvoicePurpose;
use App\Enums\AcademyInvoiceStatus;
use App\Models\Academy;
use App\Models\AcademyInvoice;
use App\Models\Application;
use App\Support\Money;
use Illuminate\Validation\ValidationException;

class ApplicationPaymentPlan
{
    public function __construct(private readonly PaystackFeeCalculator $fees) {}

    /** @return list<array<string, mixed>> */
    public function options(Academy $academy, Application $application): array
    {
        $invoices = $application->invoices()->where('status', '!=', AcademyInvoiceStatus::Void->value)
            ->with('installments')->get()->keyBy(fn (AcademyInvoice $invoice) => $invoice->purpose->value);
        $applicationInvoice = $invoices->get(AcademyInvoicePurpose::Application->value);
        $programmeInvoice = $invoices->get(AcademyInvoicePurpose::Programme->value);
        $applicationBalance = $applicationInvoice?->balanceMinor() ?? 0;
        $options = [];
        if ($applicationBalance > 0) {
            $options[] = $this->option($academy, $application->programmeCycle->currency, 'application_only', 'Pay application fee only', $applicationBalance, $applicationBalance, 0);
        }
        if ($programmeInvoice !== null && $programmeInvoice->balanceMinor() > 0) {
            $options[] = $this->option(
                $academy, $application->programmeCycle->currency, 'full', $applicationBalance > 0 ? 'Application fee + full programme fee' : 'Pay full programme fee',
                $applicationBalance + $programmeInvoice->balanceMinor(), $applicationBalance, $programmeInvoice->balanceMinor(),
            );
            foreach ($programmeInvoice->installments->sortBy('sequence') as $installment) {
                $balance = $installment->amount_minor - $installment->paid_minor - $installment->written_off_minor;
                if ($balance > 0) {
                    $options[] = $this->option(
                        $academy, $application->programmeCycle->currency, 'installment:'.$installment->sequence,
                        ($applicationBalance > 0 ? 'Application fee + ' : '').$installment->name,
                        $applicationBalance + $balance, $applicationBalance, $balance,
                        ['installment_name' => $installment->name, 'installment_sequence' => $installment->sequence],
                    );
                }
            }
        }

        return $options;
    }

    /** @return list<array{invoice_id:int, installment_id:int, amount_minor:int, label:string}> */
    public function allocations(Academy $academy, Application $application, string $selectionKey): array
    {
        $valid = collect($this->options($academy, $application))->firstWhere('key', $selectionKey);
        if ($valid === null) {
            throw ValidationException::withMessages(['selection' => 'This payment option is no longer available. Refresh and try again.']);
        }
        $applicationInvoice = $application->invoices()->where('purpose', AcademyInvoicePurpose::Application->value)
            ->where('status', '!=', AcademyInvoiceStatus::Void->value)->with('installments')->first();
        $programmeInvoice = $application->invoices()->where('purpose', AcademyInvoicePurpose::Programme->value)
            ->where('status', '!=', AcademyInvoiceStatus::Void->value)->with('installments')->first();
        $allocations = [];
        $applicationBalance = $applicationInvoice?->balanceMinor() ?? 0;
        if ($applicationBalance > 0) {
            $installment = $applicationInvoice->installments->firstOrFail();
            $allocations[] = ['invoice_id' => $applicationInvoice->id, 'installment_id' => $installment->id, 'amount_minor' => $applicationBalance, 'label' => 'Application fee'];
        }
        if ($selectionKey === 'application_only') {
            return $allocations;
        }
        if ($programmeInvoice === null) {
            throw ValidationException::withMessages(['selection' => 'Programme fees are unavailable.']);
        }
        $installments = $selectionKey === 'full'
            ? $programmeInvoice->installments->sortBy('sequence')
            : $programmeInvoice->installments->where('sequence', (int) str($selectionKey)->after(':')->toString());
        foreach ($installments as $installment) {
            $balance = $installment->amount_minor - $installment->paid_minor - $installment->written_off_minor;
            if ($balance > 0) {
                $allocations[] = ['invoice_id' => $programmeInvoice->id, 'installment_id' => $installment->id, 'amount_minor' => $balance, 'label' => $installment->name];
            }
        }

        return $allocations;
    }

    /** @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function option(Academy $academy, string $currency, string $key, string $label, int $base, int $application, int $programme, array $extra = []): array
    {
        $surcharge = $this->fees->surcharge($academy, $base);

        return [
            'key' => $key, 'label' => $label, 'currency' => $currency,
            'application_amount' => Money::fromMinor($application), 'programme_amount' => Money::fromMinor($programme),
            'base_amount' => Money::fromMinor($base), 'processing_fee' => Money::fromMinor($surcharge),
            'total_charge' => Money::fromMinor($base + $surcharge), ...$extra,
        ];
    }
}
