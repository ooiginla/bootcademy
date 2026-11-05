<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_platform_super_admin === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'monthly_ngn' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'quarterly_ngn' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'annual_ngn' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'active_player_limit' => ['required', 'integer', 'min:1', 'max:100000000'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
