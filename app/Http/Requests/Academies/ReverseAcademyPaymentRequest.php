<?php

namespace App\Http\Requests\Academies;

use App\Academies\AcademyContext;
use App\Models\AcademyPayment;
use Illuminate\Foundation\Http\FormRequest;

class ReverseAcademyPaymentRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'amount' => str_replace(',', '', trim((string) $this->input('amount'))),
            'reason' => trim((string) $this->input('reason')),
        ]);
    }

    public function authorize(): bool
    {
        $context = app(AcademyContext::class);
        $payment = $this->route('payment');
        abort_unless($payment instanceof AcademyPayment && $payment->academy_id === $context->id(), 404);

        return $context->canManageFinance();
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'regex:/^\d{1,12}(?:\.\d{1,2})?$/', 'not_in:0,0.00'],
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
            'effective_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
        ];
    }
}
