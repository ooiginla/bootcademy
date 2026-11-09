<?php

namespace App\Http\Requests\Academies;

use App\Academies\AcademyContext;
use App\Models\AcademyCycle;
use App\Models\Cohort;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveAcademyCycleFeeRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')), 'category' => trim((string) $this->input('category')),
            'is_compulsory' => $this->boolean('is_compulsory'), 'applies_to_all_programmes' => $this->boolean('applies_to_all_programmes'),
            'is_active' => $this->boolean('is_active'), 'programme_ids' => is_array($this->input('programme_ids')) ? $this->input('programme_ids') : [],
            'academy_cycle_term_id' => $this->input('academy_cycle_term_id') ?: null,
            'cohort_id' => $this->input('cohort_id') ?: null,
        ]);
    }

    public function authorize(): bool
    {
        $cycle = $this->route('cycle');
        $context = app(AcademyContext::class);
        abort_unless($cycle instanceof AcademyCycle && $cycle->academy_id === $context->id(), 404);

        return $context->canManageFinance();
    }

    public function rules(): array
    {
        $academyId = app(AcademyContext::class)->id();
        $forAcademy = fn (Builder $query): Builder => $query->where('academy_id', $academyId);

        return [
            'name' => ['required', 'string', 'max:160', Rule::unique('academy_cycle_fees', 'name')->where(
                fn (Builder $query): Builder => $query
                    ->where('academy_id', $academyId)
                    ->where('academy_cycle_id', $this->route('cycle')->id)
                    ->where('term_scope_id', (int) ($this->input('academy_cycle_term_id') ?: 0))
                    ->where('cohort_scope_id', (int) ($this->input('cohort_id') ?: 0)),
            )], 'category' => ['required', 'string', 'max:80'],
            'amount' => ['required', 'regex:/^\d{1,12}(?:\.\d{1,2})?$/', 'not_in:0,0.00'],
            'is_compulsory' => ['boolean'], 'applies_to_all_programmes' => ['boolean'], 'is_active' => ['boolean'],
            'programme_ids' => ['exclude_if:applies_to_all_programmes,true', 'required_if:applies_to_all_programmes,false', 'array', 'min:1'],
            'programme_ids.*' => ['integer', 'distinct', Rule::exists('programmes', 'id')->where($forAcademy)],
            'academy_cycle_term_id' => ['nullable', 'integer', Rule::exists('academy_cycle_terms', 'id')->where($forAcademy)],
            'cohort_id' => ['nullable', 'integer', Rule::exists('cohorts', 'id')->where($forAcademy)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var AcademyCycle $cycle */
            $cycle = $this->route('cycle');
            $termId = $this->input('academy_cycle_term_id');
            if ($termId && ! $cycle->terms()->whereKey($termId)->exists()) {
                $validator->errors()->add('academy_cycle_term_id', 'Select a term from this billing cycle.');
            }
            $cohortId = $this->input('cohort_id');
            if (! $cohortId) {
                return;
            }
            $cohort = Cohort::query()->with('programmeCycle')->whereKey($cohortId)->first();
            if ($cohort?->programmeCycle?->academy_cycle_id !== $cycle->id) {
                $validator->errors()->add('cohort_id', 'Select a cohort from this billing cycle.');

                return;
            }
            if (! $this->boolean('applies_to_all_programmes')
                && ! in_array($cohort->programmeCycle->programme_id, array_map('intval', $this->input('programme_ids', [])), true)) {
                $validator->errors()->add('cohort_id', 'The selected cohort must belong to one of the selected programmes.');
            }
        });
    }
}
