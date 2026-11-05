<?php

namespace App\Http\Requests\Academies;

use App\Academies\AcademyContext;
use App\Models\Programme;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveProgrammeRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $code = trim((string) $this->input('code'));
        $description = trim((string) $this->input('description'));

        $optionalText = ['duration', 'delivery_format', 'programme_type', 'public_timezone', 'level'];
        $normalized = [];
        foreach ($optionalText as $field) {
            $value = trim((string) $this->input($field));
            $normalized[$field] = $value === '' ? null : $value;
        }

        $this->merge([
            'name' => trim((string) $this->input('name')),
            'code' => $code === '' ? null : mb_strtoupper($code),
            'description' => $description === '' ? null : $description,
            'branch_ids' => is_array($this->input('branch_ids')) ? $this->input('branch_ids') : [],
            'tools_platforms' => $this->normalizedRows('tools_platforms', ['name', 'logo_url']),
            'faqs' => $this->normalizedRows('faqs', ['question', 'answer_html']),
            'testimonials' => $this->normalizedRows('testimonials', ['quote_html', 'name', 'role']),
            ...$normalized,
        ]);
    }

    public function authorize(): bool
    {
        $context = app(AcademyContext::class);
        $programme = $this->route('programme');

        if ($programme instanceof Programme) {
            abort_unless($programme->academy_id === $context->id(), 404);
        }

        return $this->user()?->can('manage', $context->academy()) === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $context = app(AcademyContext::class);
        $programme = $this->route('programme');
        $programmeId = $programme instanceof Programme ? $programme->id : null;
        $forAcademy = fn (Builder $query): Builder => $query->where('academy_id', $context->id());

        return [
            'name' => ['required', 'string', 'max:160', Rule::unique('programmes')->where($forAcademy)->ignore($programmeId)],
            'code' => ['nullable', 'string', 'max:32', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('programmes')->where($forAcademy)->ignore($programmeId)],
            'description' => ['nullable', 'string', 'max:2000'],
            'duration' => ['nullable', 'string', 'max:100'],
            'delivery_format' => ['nullable', 'string', 'max:100'],
            'programme_type' => ['nullable', 'string', 'max:100'],
            'public_timezone' => ['nullable', 'string', 'max:100', 'timezone:all'],
            'level' => ['nullable', 'string', 'max:100'],
            'outcomes_html' => ['nullable', 'string', 'max:50000'],
            'skills_gained_html' => ['nullable', 'string', 'max:50000'],
            'curriculum_html' => ['nullable', 'string', 'max:100000'],
            'eligibility_html' => ['nullable', 'string', 'max:50000'],
            'audience_html' => ['nullable', 'string', 'max:50000'],
            'demand_html' => ['nullable', 'string', 'max:50000'],
            'application_process_html' => ['nullable', 'string', 'max:100000'],
            'tools_platforms' => ['array', 'max:30'],
            'tools_platforms.*.name' => ['required', 'string', 'max:120'],
            'tools_platforms.*.logo_url' => ['nullable', 'url:http,https', 'max:2048'],
            'faqs' => ['array', 'max:30'],
            'faqs.*.question' => ['required', 'string', 'max:300'],
            'faqs.*.answer_html' => ['required', 'string', 'max:50000'],
            'testimonials' => ['array', 'max:30'],
            'testimonials.*.quote_html' => ['required', 'string', 'max:50000'],
            'testimonials.*.name' => ['required', 'string', 'max:160'],
            'testimonials.*.role' => ['nullable', 'string', 'max:160'],
            'is_active' => ['required', 'boolean'],
            'branch_ids' => ['array'],
            'branch_ids.*' => ['integer', 'distinct', Rule::exists('branches', 'id')->where($forAcademy)],
        ];
    }

    /** @param array<int, string> $fields
     * @return array<int, array<string, string|null>>
     */
    private function normalizedRows(string $key, array $fields): array
    {
        $rows = $this->input($key);
        if (! is_array($rows)) {
            return [];
        }

        return collect($rows)->filter(fn (mixed $row): bool => is_array($row))
            ->map(function (array $row) use ($fields): array {
                return collect($fields)->mapWithKeys(function (string $field) use ($row): array {
                    $value = trim((string) ($row[$field] ?? ''));

                    return [$field => $value === '' ? null : $value];
                })->all();
            })
            ->filter(fn (array $row): bool => collect($row)->contains(fn (?string $value): bool => $value !== null))
            ->values()->all();
    }
}
