<?php

namespace App\Http\Requests\Academies;

use App\Academies\AcademyContext;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAcademyBankDetailsRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['bank_name', 'bank_account_name', 'bank_account_number'] as $field) {
            $value = trim((string) $this->input($field));
            $this->merge([$field => $value === '' ? null : $value]);
        }
    }

    public function authorize(): bool
    {
        return app(AcademyContext::class)->canManageAcademy();
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'bank_name' => ['nullable', 'required_with:bank_account_name,bank_account_number', 'string', 'max:120'],
            'bank_account_name' => ['nullable', 'required_with:bank_name,bank_account_number', 'string', 'max:160'],
            'bank_account_number' => ['nullable', 'required_with:bank_name,bank_account_name', 'string', 'max:64', 'regex:/^[A-Za-z0-9 -]+$/'],
        ];
    }
}
