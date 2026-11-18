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
            'description' => ['nullable', 'string', 'max:255'],
            'active_player_limit' => ['required', 'integer', 'min:1', 'max:100000000'],
            'branch_limit' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'staff_account_limit' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'is_recommended' => ['required', 'boolean'],
            'migration_support' => ['nullable', 'string', 'max:80'],
            'support_level' => ['nullable', 'string', 'max:120'],
            'flags' => ['array'],
            'flags.*' => ['boolean'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
