<?php

namespace App\Http\Requests\Academies;

use App\Academies\AcademyContext;
use App\Enums\AcademyRole;
use App\Models\TrainingSession;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveTrainingSessionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['location', 'meeting_link', 'notes'] as $key) {
            $value = trim((string) $this->input($key));
            $this->merge([$key => $value === '' ? null : $value]);
        }
        $branchIds = $this->input('branch_ids', $this->filled('branch_id') ? [$this->input('branch_id')] : []);
        $coachIds = $this->input('coach_membership_ids', $this->filled('coach_membership_id') ? [$this->input('coach_membership_id')] : []);
        $this->merge([
            'title' => trim((string) $this->input('title')),
            'all_branches' => $this->boolean('all_branches'),
            'branch_ids' => is_array($branchIds) ? $branchIds : [],
            'coach_membership_ids' => is_array($coachIds) ? $coachIds : [],
            'delivery_mode' => $this->input('delivery_mode', 'physical'),
        ]);
    }

    public function authorize(): bool
    {
        $context = app(AcademyContext::class);
        $session = $this->route('trainingSession');
        if ($session instanceof TrainingSession) {
            abort_unless($session->academy_id === $context->id(), 404);
        }

        return $context->canManageAcademy();
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $academyId = app(AcademyContext::class)->id();
        $academy = fn (Builder $query): Builder => $query->where('academy_id', $academyId);
        $coach = fn (Builder $query): Builder => $query->where('academy_id', $academyId)->where('role', AcademyRole::Coach->value);

        return [
            'title' => ['required', 'string', 'max:160'],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where($academy)],
            'all_branches' => ['required', 'boolean'],
            'branch_ids' => ['array', 'required_unless:all_branches,true'],
            'branch_ids.*' => ['integer', 'distinct', Rule::exists('branches', 'id')->where($academy)],
            'programme_id' => ['required', 'integer', Rule::exists('programmes', 'id')->where($academy)],
            'cohort_id' => ['nullable', 'integer', Rule::exists('cohorts', 'id')->where($academy)],
            'coach_membership_id' => ['nullable', 'integer', Rule::exists('academy_memberships', 'id')->where($coach)],
            'coach_membership_ids' => ['array'],
            'coach_membership_ids.*' => ['integer', 'distinct', Rule::exists('academy_memberships', 'id')->where($coach)],
            'starts_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'ends_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:starts_at'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'location' => ['nullable', 'string', 'max:255'],
            'delivery_mode' => ['required', Rule::in(['physical', 'virtual'])],
            'meeting_link' => ['nullable', 'url:http,https', 'max:2048'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::in(['scheduled', 'completed', 'cancelled'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->filled('cohort_id')) {
                return;
            }
            $academy = app(AcademyContext::class)->academy();
            $cohort = $academy->cohorts()->with('programmeCycle')->find($this->integer('cohort_id'));
            if ($cohort === null) {
                return;
            }
            if ($cohort->programmeCycle->programme_id !== $this->integer('programme_id')) {
                $validator->errors()->add('cohort_id', 'The selected cohort does not belong to this programme.');
            }
            $branchIds = collect($this->input('branch_ids', []))->map(fn ($id): int => (int) $id);
            if (! $this->boolean('all_branches') && $cohort->branch_id !== null && ! $branchIds->contains($cohort->branch_id)) {
                $validator->errors()->add('branch_ids', 'The session must include the cohort branch.');
            }
        });
    }
}
