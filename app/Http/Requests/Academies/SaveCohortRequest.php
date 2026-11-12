<?php

namespace App\Http\Requests\Academies;

use App\Academies\AcademyContext;
use App\Enums\CohortStatus;
use App\Models\Cohort;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveCohortRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'code' => trim((string) $this->input('code')) ?: null,
            'branch_id' => $this->input('branch_id') ?: null,
            'ends_on' => $this->input('ends_on') ?: null,
            'capacity' => $this->input('capacity') ?: null,
            'is_receiving_applications' => $this->boolean('is_receiving_applications'),
            'applications_open_on' => $this->input('applications_open_on') ?: null,
            'applications_close_on' => $this->input('applications_close_on') ?: null,
        ]);
    }

    public function authorize(): bool
    {
        $context = app(AcademyContext::class);
        $cohort = $this->route('cohort');
        if ($cohort instanceof Cohort) {
            abort_unless($cohort->academy_id === $context->id(), 404);
        }

        return $context->canManageAcademy();
    }

    public function rules(): array
    {
        $academyId = app(AcademyContext::class)->id();
        $academy = fn (Builder $query): Builder => $query->where('academy_id', $academyId);

        return [
            'programme_cycle_id' => ['required', 'integer', Rule::exists('programme_cycles', 'id')->withoutTrashed()->where($academy)],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->withoutTrashed()->where($academy)],
            'name' => ['required', 'string', 'max:120'],
            'code' => ['nullable', 'string', 'max:64'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'delivery_mode' => ['required', Rule::in(['physical', 'virtual', 'hybrid'])],
            'status' => ['required', Rule::enum(CohortStatus::class)],
            'is_receiving_applications' => ['required', 'boolean'],
            'applications_open_on' => ['nullable', 'date'],
            'applications_close_on' => ['nullable', 'date', 'after_or_equal:applications_open_on'],
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
            $branchId = $this->input('branch_id') === null ? null : $this->integer('branch_id');
            if ($offering->branch_id !== null && $branchId !== $offering->branch_id) {
                $validator->errors()->add('branch_id', 'This cohort must use the branch configured on its programme offering.');
            }
            $cohort = $this->route('cohort');
            if ($cohort instanceof Cohort && (
                $cohort->programme_cycle_id !== $offering->id
                || $cohort->branch_id !== $branchId
            )) {
                $validator->errors()->add(
                    'programme_cycle_id',
                    'A cohort cannot be moved to another programme offering or branch. Create a new cohort instead.',
                );
            }
            $duplicate = $academy->cohorts()->where('programme_cycle_id', $offering->id)->where('name', $this->input('name'))
                ->when($cohort instanceof Cohort, fn ($query) => $query->whereKeyNot($cohort->id))->exists();
            if ($duplicate) {
                $validator->errors()->add('name', 'A cohort with this name already exists for the programme offering.');
            }
        });
    }
}
