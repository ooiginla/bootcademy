<?php

namespace App\Http\Requests\Platform;

use App\Enums\SubscriptionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignAcademySubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_platform_super_admin === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'plan_id' => [
                'required',
                Rule::exists('plans', 'id')->where('is_active', true),
            ],
            'status' => ['required', Rule::enum(SubscriptionStatus::class)],
        ];
    }
}
