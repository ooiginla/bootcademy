<?php

namespace App\Http\Requests\Public;

use App\Models\Review;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitReviewResponseRequest extends FormRequest
{
    private ?Review $resolvedReview = null;

    public function authorize(): bool
    {
        return $this->review()->status->value === 'published';
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'respondent_name' => trim((string) $this->input('respondent_name')) ?: null,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $review = $this->review();
        $fieldIds = array_map(fn (array $field): string => (string) $field['id'], $review->fields);
        $rules = [
            'respondent_name' => [
                $review->collect_respondent_name ? 'required' : 'nullable',
                'string',
                'max:160',
            ],
            'answers' => ['required', Rule::array($fieldIds)],
        ];

        foreach ($review->fields as $field) {
            $id = (string) $field['id'];
            $required = $field['required'];
            $base = $required ? ['required'] : ['nullable'];
            $key = 'answers.'.$id;

            $rules[$key] = match ($field['type']) {
                'email' => [...$base, 'string', 'email:rfc', 'max:320'],
                'phone' => [...$base, 'string', 'max:40'],
                'number' => [...$base, 'numeric'],
                'date' => [...$base, 'date_format:Y-m-d'],
                'select', 'radio' => [...$base, 'string', Rule::in($field['options'])],
                'multi_select' => [...$base, 'array', 'max:20'],
                'checkbox' => $field['options'] === []
                    ? ($required ? ['accepted'] : ['nullable', 'boolean'])
                    : [...$base, 'array', 'max:20'],
                'rating' => [...$base, 'integer', 'between:1,'.$field['max_rating']],
                'long_text' => [...$base, 'string', 'max:5000'],
                default => [...$base, 'string', 'max:1000'],
            };

            if ($field['type'] === 'multi_select' || ($field['type'] === 'checkbox' && $field['options'] !== [])) {
                $rules[$key.'.*'] = ['string', Rule::in($field['options'])];
            }
        }

        return $rules;
    }

    public function review(): Review
    {
        return $this->resolvedReview ??= Review::query()
            ->where('public_token', (string) $this->route('token'))
            ->firstOrFail();
    }
}
