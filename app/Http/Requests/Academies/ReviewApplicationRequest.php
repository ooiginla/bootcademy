<?php

namespace App\Http\Requests\Academies;

use App\Academies\AcademyContext;
use App\Models\Application;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ReviewApplicationRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $notes = trim((string) $this->input('review_notes'));
        $this->merge([
            'review_notes' => $notes === '' ? null : $notes,
            'programme_id' => $this->input('programme_id') ?: null,
            'branch_id' => $this->input('branch_id') ?: null,
        ]);
    }

    public function authorize(): bool
    {
        $context = app(AcademyContext::class);
        $application = $this->route('application');
        abort_unless($application instanceof Application && $application->academy_id === $context->id(), 404);

        return $context->canManageAcademy();
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $academyId = app(AcademyContext::class)->id();
        $forAcademy = fn (Builder $query): Builder => $query->where('academy_id', $academyId);

        return [
            'decision' => ['required', Rule::in(['review', 'accept', 'reject', 'enrol'])],
            'review_notes' => ['nullable', 'string', 'max:2000'],
            'programme_id' => ['nullable', 'integer', Rule::exists('programmes', 'id')->withoutTrashed()->where($forAcademy)],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->withoutTrashed()->where($forAcademy)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $application = $this->route('application');
            if (in_array($this->input('decision'), ['accept', 'enrol'], true)
                && $this->input('programme_id') === null
                && $application instanceof Application
                && $application->programme_id === null) {
                $validator->errors()->add('programme_id', 'Select a programme before accepting this application.');
            }
        });
    }
}
