<?php

namespace App\Http\Requests\Academies;

use App\Academies\AcademyContext;
use App\Enums\ReviewAudience;
use App\Enums\ReviewFieldType;
use App\Enums\ReviewStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveReviewRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' => trim((string) $this->input('title')),
            'description' => trim((string) $this->input('description')) ?: null,
        ]);
    }

    public function authorize(): bool
    {
        return app(AcademyContext::class)->canManageAcademy();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160', 'regex:/^[^\pC<>]+$/u'],
            'description' => ['nullable', 'string', 'max:3000'],
            'audience' => ['required', Rule::enum(ReviewAudience::class)],
            'status' => ['required', Rule::enum(ReviewStatus::class)],
            'collect_respondent_name' => ['required', 'boolean'],
            'fields' => ['required', 'array', 'min:1', 'max:50'],
            'fields.*.id' => ['required', 'string', 'max:64', 'regex:/^[a-zA-Z0-9_-]+$/', 'distinct'],
            'fields.*.label' => ['required', 'string', 'max:200'],
            'fields.*.description' => ['nullable', 'string', 'max:500'],
            'fields.*.type' => ['required', Rule::enum(ReviewFieldType::class)],
            'fields.*.required' => ['required', 'boolean'],
            'fields.*.options' => ['nullable', 'array', 'max:20'],
            'fields.*.options.*' => ['string', 'max:120'],
            'fields.*.max_rating' => ['nullable', 'integer', 'between:2,10'],
        ];
    }

    /** @param Validator $validator */
    public function withValidator($validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach ((array) $this->input('fields', []) as $index => $field) {
                if (! is_array($field)) {
                    continue;
                }

                $type = ReviewFieldType::tryFrom((string) ($field['type'] ?? ''));
                $options = array_values(array_filter(array_map(
                    fn (mixed $option): string => trim((string) $option),
                    is_array($field['options'] ?? null) ? $field['options'] : [],
                ), fn (string $option): bool => $option !== ''));

                if ($type?->needsOptions() && count($options) < 2) {
                    $validator->errors()->add("fields.$index.options", 'Add at least two options for this field.');
                }

                if (count($options) !== count(array_unique(array_map('mb_strtolower', $options)))) {
                    $validator->errors()->add("fields.$index.options", 'Options must be unique.');
                }
            }
        });
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'title.regex' => 'The review title cannot contain markup or control characters.',
            'fields.min' => 'Add at least one form field.',
        ];
    }
}
