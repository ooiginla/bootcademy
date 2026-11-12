<?php

namespace App\Http\Requests\Academies;

use App\Academies\AcademyContext;
use App\Models\ApplicationIntake;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveApplicationIntakeRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'cohort_id' => $this->input('cohort_id') ?: null,
            'branch_id' => $this->input('branch_id') ?: null,
            'opens_on' => $this->input('opens_on') ?: null,
            'closes_on' => $this->input('closes_on') ?: null,
            'is_enabled' => $this->boolean('is_enabled'),
        ]);
    }

    public function authorize(): bool
    {
        $context = app(AcademyContext::class);
        $intake = $this->route('applicationIntake');
        if ($intake instanceof ApplicationIntake) {
            abort_unless($intake->academy_id === $context->id(), 404);
        }

        return $context->canManageAcademy();
    }

    public function rules(): array
    {
        $academyId = app(AcademyContext::class)->id();
        $academy = fn (Builder $query): Builder => $query->where('academy_id', $academyId);

        return [
            'programme_cycle_id' => ['required', 'integer', Rule::exists('programme_cycles', 'id')->withoutTrashed()->where($academy)],
            'cohort_id' => ['nullable', 'integer', Rule::exists('cohorts', 'id')->where($academy)],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->withoutTrashed()->where($academy)],
            'opens_on' => ['nullable', 'date'],
            'closes_on' => ['nullable', 'date', 'after_or_equal:opens_on'],
            'is_enabled' => ['required', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $academy = app(AcademyContext::class)->academy();
            $offering = $academy->programmeCycles()->find($this->integer('programme_cycle_id'));
            if ($offering === null) {
                return;
            }
            $cohort = $this->filled('cohort_id') ? $academy->cohorts()->find($this->integer('cohort_id')) : null;
            if ($cohort !== null && $cohort->programme_cycle_id !== $offering->id) {
                $validator->errors()->add('cohort_id', 'The selected cohort does not belong to this programme offering.');
            }
            $branchId = $this->filled('branch_id') ? $this->integer('branch_id') : null;
            $requiredBranch = $cohort?->branch_id ?? $offering->branch_id;
            if ($requiredBranch !== null && $branchId !== $requiredBranch) {
                $validator->errors()->add('branch_id', 'Use the branch configured for the selected cohort or programme offering.');
            }
            $intake = $this->route('applicationIntake');
            $effectiveBranchId = $requiredBranch ?? $branchId;
            if ($intake instanceof ApplicationIntake && $intake->applications()->exists() && (
                $intake->programme_cycle_id !== $offering->id
                || $intake->cohort_id !== $cohort?->id
                || $intake->branch_id !== $effectiveBranchId
            )) {
                $validator->errors()->add(
                    'programme_cycle_id',
                    'This setting already has applications. Keep its scope for audit history and create a new setting instead.',
                );
            }
            $duplicate = $academy->applicationIntakes()
                ->where('programme_cycle_id', $offering->id)
                ->where('cohort_scope_id', $cohort?->id ?? 0)
                ->where('branch_scope_id', $branchId ?? 0)
                ->when($intake instanceof ApplicationIntake, fn ($query) => $query->whereKeyNot($intake->id))
                ->exists();
            if ($duplicate) {
                $validator->errors()->add('programme_cycle_id', 'An application setting already exists for this offering, cohort and branch.');
            }
        });
    }
}
