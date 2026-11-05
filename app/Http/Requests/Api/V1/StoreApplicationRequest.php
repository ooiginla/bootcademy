<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\CustomFieldType;
use App\Models\Academy;
use App\Models\AcademyCustomField;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Validator;

class StoreApplicationRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        foreach ([
            'internal_identifier', 'middle_name', 'gender', 'phone', 'whatsapp_number', 'home_address', 'city',
            'state_region', 'country', 'guardian_first_name', 'guardian_last_name', 'guardian_email',
            'guardian_phone', 'guardian_whatsapp_number', 'guardian_relationship',
        ] as $key) {
            $value = trim((string) $this->input($key));
            $this->merge([$key => $value === '' ? null : $value]);
        }
        $this->merge([
            'external_reference' => trim((string) $this->input('external_reference')),
            'first_name' => trim((string) $this->input('first_name')),
            'last_name' => trim((string) $this->input('last_name')),
            'internal_identifier' => $this->input('internal_identifier') === null
                ? null
                : mb_strtoupper((string) $this->input('internal_identifier')),
            'programme_id' => $this->input('programme_id') ?: null,
            'programme_cycle_id' => $this->input('programme_cycle_id') ?: null,
            'branch_id' => $this->input('branch_id') ?: null,
            'privacy_policy_version' => trim((string) $this->input('privacy_policy_version')),
            'custom_fields' => is_array($this->input('custom_fields')) ? $this->input('custom_fields') : [],
        ]);
    }

    public function authorize(): bool
    {
        return $this->attributes->get('api_academy') instanceof Academy;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $academy = $this->academy();
        $forAcademy = fn ($query) => $query->where('academy_id', $academy->id);
        $rules = [
            'external_reference' => ['required', 'string', 'max:120'],
            'consent_accepted' => ['required', 'accepted'],
            'privacy_policy_version' => ['required', 'string', 'max:60'],
            'internal_identifier' => ['nullable', 'string', 'max:64', Rule::unique('players')->where($forAcademy)],
            'first_name' => ['required', 'string', 'max:100'], 'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'], 'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'gender' => ['nullable', Rule::in(['Male', 'Female'])], 'phone' => ['nullable', 'string', 'max:32'],
            'whatsapp_number' => ['nullable', 'string', 'max:32'], 'home_address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'], 'state_region' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', Rule::in(config('countries'))],
            'guardian_first_name' => ['nullable', 'string', 'max:100'],
            'guardian_last_name' => ['required_with:guardian_first_name', 'nullable', 'string', 'max:100'],
            'guardian_email' => ['nullable', 'email:rfc', 'max:254'],
            'guardian_phone' => ['required_with:guardian_first_name', 'nullable', 'string', 'max:32'],
            'guardian_whatsapp_number' => ['nullable', 'string', 'max:32'],
            'guardian_relationship' => ['nullable', 'string', 'max:80'],
            'guardian_signature' => ['nullable', File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max(2 * 1024)],
            'programme_id' => ['nullable', 'integer', Rule::exists('programmes', 'id')->where($forAcademy)],
            'programme_cycle_id' => ['nullable', 'integer', Rule::exists('programme_cycles', 'id')->where($forAcademy)],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where($forAcademy)],
        ];
        $fields = $this->fields();
        $rules['custom_fields'] = ['array'];
        foreach ($fields as $field) {
            $rules["custom_fields.{$field->key}"] = [
                $field->is_required ? 'required' : 'nullable',
                ...match ($field->type) {
                    CustomFieldType::Text => ['string', 'max:500'], CustomFieldType::Textarea => ['string', 'max:5000'],
                    CustomFieldType::Number => ['numeric'], CustomFieldType::Date => ['date'],
                    CustomFieldType::Boolean => ['boolean'], CustomFieldType::Select => [Rule::in($field->options ?? [])],
                },
            ];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $allowed = $this->fields()->pluck('key')->all();
            foreach (array_diff(array_keys($this->input('custom_fields', [])), $allowed) as $key) {
                $validator->errors()->add("custom_fields.{$key}", 'This custom field is not available for this academy.');
            }
        });
    }

    private function academy(): Academy
    {
        $academy = $this->attributes->get('api_academy');
        abort_unless($academy instanceof Academy, 401);

        return $academy;
    }

    /** @return Collection<int, AcademyCustomField> */
    private function fields(): Collection
    {
        return $this->academy()->customFields()
            ->where('entity_type', 'player')->where('is_active', true)->get();
    }
}
