<?php

namespace App\Http\Requests\Academies;

use App\Academies\AcademyContext;
use App\Models\AcademyCycle;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $academyId = app(AcademyContext::class)->id();
        $forAcademy = fn (Builder $query): Builder => $query->where('academy_id', $academyId);

        return [
            'enrollment_ids' => ['required', 'array', 'min:1', 'max:200'],
            'enrollment_ids.*' => ['integer', 'distinct', Rule::exists('enrollments', 'id')->where($forAcademy)],
            'academy_cycle_id' => ['required', 'integer', Rule::exists('academy_cycles', 'id')->withoutTrashed()->where($forAcademy)],
            'programme_id' => ['required', 'integer', Rule::exists('programmes', 'id')->withoutTrashed()->where($forAcademy)],
            'academy_cycle_term_id' => ['required', 'integer', Rule::exists('academy_cycle_terms', 'id')->where($forAcademy)],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->withoutTrashed()->where($forAcademy)],
            'cohort_id' => ['nullable', 'integer', Rule::exists('cohorts', 'id')->where($forAcademy)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $cycleId = $this->input('academy_cycle_id');
            if (! $cycleId) {
                return;
            }

            $cycle = AcademyCycle::query()
                ->where('academy_id', app(AcademyContext::class)->id())
                ->whereKey((int) $cycleId)
                ->first();
            if ($cycle !== null && ! $cycle->supportsTerms()) {
                $validator->errors()->add('academy_cycle_id', 'Rolling cycles do not use term re-enrollment.');
            }
        });
    }
}
