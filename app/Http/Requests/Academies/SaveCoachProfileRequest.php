<?php

namespace App\Http\Requests\Academies;

use App\Academies\AcademyContext;
use App\Enums\AcademyRole;
use App\Enums\CustomFieldType;
use App\Models\AcademyCustomField;
use App\Models\AcademyMembership;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveCoachProfileRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'first_name' => trim((string) $this->input('first_name')),
            'middle_name' => $this->nullableString('middle_name'),
            'last_name' => trim((string) $this->input('last_name')),
            'gender' => $this->nullableString('gender'),
            'phone' => $this->nullableString('phone'),
            'whatsapp_number' => $this->nullableString('whatsapp_number'),
            'home_address' => $this->nullableString('home_address'),
            'city' => $this->nullableString('city'),
            'state_region' => $this->nullableString('state_region'),
            'country' => $this->nullableString('country'),
            'biography' => $this->nullableString('biography'),
            'custom_fields' => is_array($this->input('custom_fields')) ? $this->input('custom_fields') : [],
        ]);
    }

    public function authorize(): bool
    {
        $context = app(AcademyContext::class);
        $membership = $this->route('membership');
        if ($membership instanceof AcademyMembership) {
            abort_unless(
                $membership->academy_id === $context->id() && $membership->role === AcademyRole::Coach,
                404,
            );
        }

        return $context->canManageAcademy();
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $rules = [
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'gender' => ['nullable', Rule::in(['male', 'female'])],
            'phone' => ['nullable', 'string', 'max:32'],
            'whatsapp_number' => ['nullable', 'string', 'max:32'],
            'home_address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'state_region' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'years_of_experience' => ['nullable', 'integer', 'min:0', 'max:80'],
            'biography' => ['nullable', 'string', 'max:5000'],
        ];

        $fields = AcademyCustomField::query()
            ->where('academy_id', app(AcademyContext::class)->id())
            ->where('entity_type', 'coach')->where('is_active', true)->get();
        $keys = $fields->pluck('key')->all();
        $rules['custom_fields'] = ['array'.($keys === [] ? '' : ':'.implode(',', $keys))];

        foreach ($fields as $field) {
            $rules["custom_fields.{$field->key}"] = [
                $field->is_required ? 'required' : 'nullable',
                ...match ($field->type) {
                    CustomFieldType::Text => ['string', 'max:500'],
                    CustomFieldType::Textarea => ['string', 'max:5000'],
                    CustomFieldType::Number => ['numeric'],
                    CustomFieldType::Date => ['date'],
                    CustomFieldType::Boolean => ['boolean'],
                    CustomFieldType::Select => [Rule::in($field->options ?? [])],
                },
            ];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $submitted = array_keys($this->input('custom_fields', []));
            $allowed = AcademyCustomField::query()
                ->where('academy_id', app(AcademyContext::class)->id())
                ->where('entity_type', 'coach')->where('is_active', true)->pluck('key')->all();
            foreach (array_diff($submitted, $allowed) as $key) {
                $validator->errors()->add("custom_fields.$key", 'This custom field is not available for this academy.');
            }
        });
    }

    private function nullableString(string $key): ?string
    {
        $value = trim((string) $this->input($key));

        return $value === '' ? null : $value;
    }
}
