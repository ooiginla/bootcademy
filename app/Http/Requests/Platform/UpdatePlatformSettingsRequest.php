<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePlatformSettingsRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['paystack_cap', 'paystack_waiver_threshold'] as $field) {
            if (trim((string) $this->input($field)) === '') {
                $this->merge([$field => null]);
            }
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->is_platform_super_admin === true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'paystack_percentage' => ['required', 'numeric', 'min:0', 'max:20', 'decimal:0,2'],
            'paystack_fixed_fee' => ['required', 'numeric', 'min:0', 'max:1000000', 'decimal:0,2'],
            'paystack_cap' => ['nullable', 'numeric', 'min:0', 'max:1000000', 'decimal:0,2'],
            'paystack_waiver_threshold' => ['nullable', 'numeric', 'min:0', 'max:1000000', 'decimal:0,2'],
            'platform_percentage' => ['required', 'numeric', 'min:0', 'max:20', 'decimal:0,2'],
            'platform_fixed_fee' => ['required', 'numeric', 'min:0', 'max:1000000', 'decimal:0,2'],
        ];
    }
}
