<?php

namespace App\Http\Requests\Academies;

use App\Academies\AcademyContext;
use App\Models\AcademyInvoice;
use Illuminate\Foundation\Http\FormRequest;

class WriteOffAcademyInvoiceRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['reason' => trim((string) $this->input('reason'))]);
    }

    public function authorize(): bool
    {
        $invoice = $this->route('invoice');
        $context = app(AcademyContext::class);
        abort_unless($invoice instanceof AcademyInvoice && $invoice->academy_id === $context->id(), 404);

        return $context->canManageFinance();
    }

    public function rules(): array
    {
        return ['amount' => ['required', 'regex:/^\d{1,12}(?:\.\d{1,2})?$/', 'not_in:0,0.00'], 'reason' => ['required', 'string', 'min:3', 'max:2000']];
    }
}
