<?php

namespace App\Http\Requests\Academies;

use App\Academies\AcademyContext;
use App\Models\ExpenseCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveExpenseCategoryRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['name' => trim((string) $this->input('name'))]);
    }

    public function authorize(): bool
    {
        $context = app(AcademyContext::class);
        $category = $this->route('category');
        if ($category instanceof ExpenseCategory) {
            abort_unless($category->academy_id === $context->id(), 404);
        }

        return $context->canManageFinance();
    }

    public function rules(): array
    {
        $category = $this->route('category');

        return ['name' => [
            'required', 'string', 'max:80',
            Rule::unique('expense_categories')->where('academy_id', app(AcademyContext::class)->id())->ignore($category instanceof ExpenseCategory ? $category->id : null),
        ]];
    }
}
