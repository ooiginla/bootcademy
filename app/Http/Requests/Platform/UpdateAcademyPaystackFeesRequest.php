<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;

/** The academy's own platform fee. Leave both blank to use the platform default. */
class UpdateAcademyPaystackFeesRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['platform_percentage', 'platform_fixed_fee'] as $field) {
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
            'platform_percentage' => ['nullable', 'numeric', 'min:0', 'max:20', 'decimal:0,2'],
            'platform_fixed_fee' => ['nullable', 'numeric', 'min:0', 'max:1000000', 'decimal:0,2'],
        ];
    }
}
