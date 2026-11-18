<?php

namespace App\Http\Requests\Academies;

use App\Academies\AcademyContext;
use App\Models\Coupon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCouponRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper(trim((string) $this->input('code'))),
            'name' => trim((string) $this->input('name')),
            'is_active' => $this->boolean('is_active'),
            'applies_to' => is_array($this->input('applies_to')) ? array_values(array_unique($this->input('applies_to'))) : [],
            'maximum_redemptions' => $this->input('maximum_redemptions') ?: null,
            'per_customer_limit' => $this->input('per_customer_limit') ?: null,
            'minimum_amount' => $this->input('minimum_amount') ?: null,
            'starts_on' => $this->input('starts_on') ?: null,
            'ends_on' => $this->input('ends_on') ?: null,
        ]);
    }

    public function authorize(): bool
    {
        $context = app(AcademyContext::class);
        $coupon = $this->route('coupon');
        abort_if($coupon instanceof Coupon && $coupon->academy_id !== $context->id(), 404);

        return $context->canManageFinance();
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $academyId = app(AcademyContext::class)->id();
        $coupon = $this->route('coupon');

        return [
            'code' => ['required', 'string', 'max:64', 'regex:/^[A-Z0-9][A-Z0-9_-]*$/', Rule::unique('coupons', 'code')->where('academy_id', $academyId)->ignore($coupon)],
            'name' => ['required', 'string', 'max:160'],
            'discount_type' => ['required', Rule::in(['percentage', 'fixed'])],
            'percentage' => ['nullable', 'required_if:discount_type,percentage', 'numeric', 'gt:0', 'lte:100', 'decimal:0,2'],
            'fixed_amount' => ['nullable', 'required_if:discount_type,fixed', 'regex:/^\d{1,12}(?:\.\d{1,2})?$/', 'not_in:0,0.00'],
            'applies_to' => ['required', 'array', 'min:1'],
            'applies_to.*' => ['string', 'distinct', Rule::in(['application_fee', 'programme_fee', 'cycle_fees', 'store'])],
            'minimum_amount' => ['nullable', 'regex:/^\d{1,12}(?:\.\d{1,2})?$/'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'maximum_redemptions' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'per_customer_limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'code.regex' => 'Use uppercase letters, numbers, hyphens or underscores only.',
            'applies_to.min' => 'Select at least one place where this coupon applies.',
        ];
    }
}
