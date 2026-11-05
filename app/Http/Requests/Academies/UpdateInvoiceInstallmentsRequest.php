<?php

namespace App\Http\Requests\Academies;

use App\Academies\AcademyContext;
use App\Enums\AcademyCycleType;
use App\Models\AcademyInvoice;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

class UpdateInvoiceInstallmentsRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['installments' => is_array($this->input('installments')) ? $this->input('installments') : []]);
    }

    public function authorize(): bool
    {
        $context = app(AcademyContext::class);
        $invoice = $this->route('invoice');
        abort_unless($invoice instanceof AcademyInvoice && $invoice->academy_id === $context->id(), 404);

        return $context->canManageFinance();
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $invoice = $this->invoice();
        $fixed = $invoice->cycle->type === AcademyCycleType::Fixed;

        return [
            'installments' => ['required', 'array', 'min:1', 'max:24'],
            'installments.*.name' => ['required', 'string', 'max:120'],
            'installments.*.amount' => ['required', 'regex:/^\d{1,12}(?:\.\d{1,2})?$/', 'not_in:0,0.00'],
            'installments.*.due_on' => [$fixed ? 'required' : 'nullable', 'nullable', 'date'],
            'installments.*.due_offset_days' => [$fixed ? 'nullable' : 'required', 'nullable', 'integer', 'min:0', 'max:3650'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $invoice = $this->invoice();
            if ($invoice->paid_minor !== 0) {
                $validator->errors()->add('installments', 'Installments cannot be changed after a payment has been allocated.');

                return;
            }
            $installments = $this->input('installments', []);
            if (! is_array($installments)) {
                return;
            }
            $total = 0;
            foreach ($installments as $index => $installment) {
                if (! is_array($installment)) {
                    continue;
                }
                $total += Money::toMinor((string) $installment['amount']);
                if ($invoice->cycle->type === AcademyCycleType::Fixed) {
                    $due = Carbon::parse($installment['due_on']);
                    if ($due->lt($invoice->cycle->starts_on) || $due->gt($invoice->cycle->ends_on)) {
                        $validator->errors()->add("installments.{$index}.due_on", 'Due dates must fall inside the invoice cycle.');
                    }
                } elseif ((int) $installment['due_offset_days'] > $invoice->cycle->maximumRollingDays()) {
                    $validator->errors()->add("installments.{$index}.due_offset_days", 'The due offset must fall inside the rolling duration.');
                }
            }
            if ($total !== $invoice->total_minor) {
                $validator->errors()->add('installments', 'The custom installments must add up exactly to the invoice total.');
            }
        });
    }

    private function invoice(): AcademyInvoice
    {
        $invoice = $this->route('invoice');
        abort_unless($invoice instanceof AcademyInvoice, 404);

        return $invoice->loadMissing('cycle');
    }
}
