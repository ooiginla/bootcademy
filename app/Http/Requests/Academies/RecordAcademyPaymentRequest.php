<?php

namespace App\Http\Requests\Academies;

use App\Academies\AcademyContext;
use App\Enums\AcademyPaymentMethod;
use App\Models\AcademyInvoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordAcademyPaymentRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'amount' => trim((string) $this->input('amount')),
            'external_reference' => $this->nullableString('external_reference'),
            'notes' => $this->nullableString('notes'),
        ]);
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
        return [
            'amount' => ['required', 'regex:/^\d{1,12}(?:\.\d{1,2})?$/', 'not_in:0,0.00'],
            'method' => ['required', Rule::enum(AcademyPaymentMethod::class), Rule::notIn([AcademyPaymentMethod::Paystack->value])],
            'received_on' => ['required', 'date', 'before_or_equal:today'],
            'external_reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    private function nullableString(string $key): ?string
    {
        $value = trim((string) $this->input($key));

        return $value === '' ? null : $value;
    }
}
