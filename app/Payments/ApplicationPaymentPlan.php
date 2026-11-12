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
        $invoices = $application->invoices()
            ->where('status', '!=', AcademyInvoiceStatus::Void->value)
            ->with(['installments', 'cycleFee'])
            ->get();
        $applicationInvoice = $invoices->firstWhere('purpose', AcademyInvoicePurpose::Application);
        $programmeInvoice = $invoices->firstWhere('purpose', AcademyInvoicePurpose::Programme);
        $cycleFeeInvoices = $invoices->whereNotNull('academy_cycle_fee_id')->values();
        $applicationBalance = $applicationInvoice?->balanceMinor() ?? 0;
        $programmeInstallment = $programmeInvoice?->installments->sortBy('sequence')->first(
            fn ($installment): bool => $installment->amount_minor - $installment->paid_minor - $installment->written_off_minor > 0,
        );
        $programmeBalance = $programmeInstallment === null
            ? 0
            : $programmeInstallment->amount_minor - $programmeInstallment->paid_minor - $programmeInstallment->written_off_minor;
        $cycleFeeBalances = $cycleFeeInvoices->map(function (AcademyInvoice $invoice): array {
            return [
                'invoice' => $invoice,
                'balance' => $invoice->balanceMinor(),
            ];
        })->filter(fn (array $item): bool => $item['balance'] > 0)->values();
        $cycleFeeBalance = (int) $cycleFeeBalances->sum('balance');
        $base = $applicationBalance + $programmeBalance + $cycleFeeBalance;
        if ($base === 0) {
            return [];
        }

        $planName = $programmeInvoice?->programmeCyclePaymentPlan?->name;
        $programmeLabel = $programmeInstallment?->name;
        $labelParts = array_values(array_filter([
            $applicationBalance > 0 ? 'Application fee' : null,
            $programmeLabel,
            $cycleFeeBalance > 0 ? 'selected cycle fees' : null,
        ]));

        return [$this->option(
            $academy,
            $application->programmeCycle->currency,
            'initial',
            implode(' + ', $labelParts),
            $base,
            $applicationBalance,
            $programmeBalance,
            [
                'payment_plan_name' => $planName,
                'programme_label' => $programmeLabel,
                'cycle_fee_amount' => Money::fromMinor($cycleFeeBalance),
                'cycle_fees' => $cycleFeeBalances->map(fn (array $item): array => [
                    'name' => $item['invoice']->fee_name ?? $item['invoice']->cycleFee->name,
                    'amount' => Money::fromMinor($item['balance']),
                ])->all(),
            ],
        )];
    }

    /** @return list<array{invoice_id:int, installment_id:int, amount_minor:int, label:string}> */
    public function allocations(Academy $academy, Application $application, string $selectionKey): array
    {
        $valid = collect($this->options($academy, $application))->firstWhere('key', $selectionKey);
        if ($valid === null) {
            throw ValidationException::withMessages(['selection' => 'This payment option is no longer available. Refresh and try again.']);
        }
        $invoices = $application->invoices()
            ->where('status', '!=', AcademyInvoiceStatus::Void->value)
            ->with('installments')
            ->get();
        $applicationInvoice = $invoices->firstWhere('purpose', AcademyInvoicePurpose::Application);
        $programmeInvoice = $invoices->firstWhere('purpose', AcademyInvoicePurpose::Programme);
        $allocations = [];
        $applicationBalance = $applicationInvoice?->balanceMinor() ?? 0;
        if ($applicationBalance > 0) {
            $installment = $applicationInvoice->installments->firstOrFail();
            $allocations[] = ['invoice_id' => $applicationInvoice->id, 'installment_id' => $installment->id, 'amount_minor' => $applicationBalance, 'label' => 'Application fee'];
        }
        $programmeInstallment = $programmeInvoice?->installments->sortBy('sequence')->first(
            fn ($installment): bool => $installment->amount_minor - $installment->paid_minor - $installment->written_off_minor > 0,
        );
        if ($programmeInvoice !== null && $programmeInstallment !== null) {
            $balance = $programmeInstallment->amount_minor - $programmeInstallment->paid_minor - $programmeInstallment->written_off_minor;
            $allocations[] = [
                'invoice_id' => $programmeInvoice->id,
                'installment_id' => $programmeInstallment->id,
                'amount_minor' => $balance,
                'label' => $programmeInstallment->name,
            ];
        }
        foreach ($invoices->whereNotNull('academy_cycle_fee_id') as $invoice) {
            $installment = $invoice->installments->first();
            if ($installment === null) {
                continue;
            }
            $balance = $installment->amount_minor - $installment->paid_minor - $installment->written_off_minor;
            if ($balance > 0) {
                $allocations[] = [
                    'invoice_id' => $invoice->id,
                    'installment_id' => $installment->id,
                    'amount_minor' => $balance,
                    'label' => $installment->name,
                ];
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
