<?php

namespace App\Http\Requests\Academies;

use App\Academies\AcademyContext;
use App\Models\ExpenseVendor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveExpenseVendorRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['name', 'email', 'phone', 'address'] as $key) {
            $value = trim((string) $this->input($key));
            $this->merge([$key => $value === '' ? null : $value]);
        }
    }

    public function authorize(): bool
    {
        $context = app(AcademyContext::class);
        $vendor = $this->route('vendor');
        if ($vendor instanceof ExpenseVendor) {
            abort_unless($vendor->academy_id === $context->id(), 404);
        }

        return $context->canManageFinance();
    }

    public function rules(): array
    {
        $vendor = $this->route('vendor');

        return [
            'name' => ['required', 'string', 'max:160', Rule::unique('expense_vendors')->where('academy_id', app(AcademyContext::class)->id())->ignore($vendor instanceof ExpenseVendor ? $vendor->id : null)],
            'email' => ['nullable', 'email:rfc', 'max:254'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:255'],
        ];
    }
}
