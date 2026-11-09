<?php

namespace App\Http\Requests\Academies;

use App\Academies\AcademyContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkReenrollProgrammeMembersRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'branch_id' => $this->input('branch_id') ?: null,
            'cohort_id' => $this->input('cohort_id') ?: null,
        ]);
    }

    public function authorize(): bool
    {
        return app(AcademyContext::class)->canManageAcademy();
    }

    public function rules(): array
    {
        $academyId = app(AcademyContext::class)->id();
        $forAcademy = fn (Builder $query): Builder => $query->where('academy_id', $academyId);

        return [
            'enrollment_ids' => ['required', 'array', 'min:1', 'max:200'],
            'enrollment_ids.*' => ['integer', 'distinct', Rule::exists('enrollments', 'id')->where($forAcademy)],
            'academy_cycle_id' => ['required', 'integer', Rule::exists('academy_cycles', 'id')->where($forAcademy)],
            'programme_id' => ['required', 'integer', Rule::exists('programmes', 'id')->where($forAcademy)],
            'academy_cycle_term_id' => ['required', 'integer', Rule::exists('academy_cycle_terms', 'id')->where($forAcademy)],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where($forAcademy)],
            'cohort_id' => ['nullable', 'integer', Rule::exists('cohorts', 'id')->where($forAcademy)],
        ];
    }
}
