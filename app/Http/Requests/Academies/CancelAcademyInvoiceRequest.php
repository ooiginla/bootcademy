<?php

namespace App\Http\Requests\Academies;

use App\Academies\AcademyContext;
use App\Models\AcademyInvoice;
use Illuminate\Foundation\Http\FormRequest;

class CancelAcademyInvoiceRequest extends FormRequest
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
        return ['reason' => ['required', 'string', 'min:3', 'max:2000']];
    }
}
