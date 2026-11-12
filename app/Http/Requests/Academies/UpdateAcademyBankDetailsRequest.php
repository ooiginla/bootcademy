<?php

namespace App\Http\Requests\Academies;

use App\Academies\AcademyContext;
use Illuminate\Foundation\Http\FormRequest;

/**
 * With Paystack configured, the academy picks its bank from Paystack's list and the account name
 * is confirmed with the bank. Without Paystack (local setups), the details are typed in by hand.
 */
class UpdateAcademyBankDetailsRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['bank_code', 'bank_name', 'bank_account_name', 'bank_account_number'] as $field) {
            $value = trim((string) $this->input($field));
            $this->merge([$field => $value === '' ? null : $value]);
        }
    }

    public function authorize(): bool
    {
        return app(AcademyContext::class)->canManageAcademy();
    }

    public function usesPaystack(): bool
    {
        return filled(config('services.paystack.secret_key'));
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        if ($this->usesPaystack()) {
            return [
                'bank_code' => ['nullable', 'required_with:bank_account_number', 'string', 'max:20', 'regex:/^[A-Za-z0-9-]+$/'],
                'bank_account_number' => ['nullable', 'required_with:bank_code', 'string', 'digits:10'],
            ];
        }

        return [
            'bank_name' => ['nullable', 'required_with:bank_account_name,bank_account_number', 'string', 'max:120'],
            'bank_account_name' => ['nullable', 'required_with:bank_name,bank_account_number', 'string', 'max:160'],
            'bank_account_number' => ['nullable', 'required_with:bank_name,bank_account_name', 'string', 'max:64', 'regex:/^[A-Za-z0-9 -]+$/'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['bank_account_number.digits' => 'Enter the 10-digit account number.'];
    }
}
