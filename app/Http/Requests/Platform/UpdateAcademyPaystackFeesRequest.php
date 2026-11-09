<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAcademyPaystackFeesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_platform_super_admin === true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'percentage' => ['required', 'numeric', 'min:0', 'max:20', 'decimal:0,2'],
            'fixed_fee' => ['required', 'numeric', 'min:0', 'max:1000000', 'decimal:0,2'],
            'cap' => ['nullable', 'numeric', 'min:0', 'max:1000000', 'decimal:0,2'],
            'waiver_threshold' => ['nullable', 'numeric', 'min:0', 'max:1000000', 'decimal:0,2'],
        ];
    }
}
