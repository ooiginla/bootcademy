<?php

namespace App\Http\Requests\Academies;

use App\Academies\AcademyContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class SaveAcademyExpenseRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['description', 'vendor', 'reference'] as $key) {
            $value = trim((string) $this->input($key));
            $this->merge([$key => $value === '' ? null : $value]);
        }
        $this->merge(['amount' => str_replace(',', '', (string) $this->input('amount'))]);
    }

    public function authorize(): bool
    {
        return app(AcademyContext::class)->canManageFinance();
    }

    public function rules(): array
    {
        $academyId = app(AcademyContext::class)->id();
        $tenantExists = fn (string $table) => Rule::exists($table, 'id')
            ->where(fn (Builder $query) => $query->where('academy_id', $academyId));

        return [
            'expense_category_id' => ['required', 'integer', $tenantExists('expense_categories')],
            'expense_vendor_id' => ['nullable', 'integer', $tenantExists('expense_vendors')],
            'branch_id' => ['nullable', 'integer', $tenantExists('branches')],
            'academy_cycle_id' => ['nullable', 'integer', $tenantExists('academy_cycles')],
            'description' => ['required', 'string', 'max:200'],
            'vendor' => ['nullable', 'string', 'max:160'],
            'reference' => ['nullable', 'string', 'max:120'],
            'amount' => ['required', 'regex:/^\d{1,12}(?:\.\d{1,2})?$/', 'not_in:0,0.00'],
            'incurred_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['required', File::types(['pdf', 'jpg', 'jpeg', 'png', 'webp'])->max(5 * 1024)],
        ];
    }
}
