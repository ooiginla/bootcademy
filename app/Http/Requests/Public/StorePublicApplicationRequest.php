<?php

namespace App\Http\Requests\Public;

use App\Academies\ApplicationIntakeAvailability;
use App\Enums\CustomFieldType;
use App\Models\Academy;
use App\Models\AcademyCustomField;
use App\Models\AcademyWebsite;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Validator;

class StorePublicApplicationRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        foreach ([
            'middle_name', 'gender', 'phone', 'whatsapp_number', 'home_address', 'city', 'state_region',
            'guardian_first_name', 'guardian_last_name', 'guardian_email', 'guardian_phone',
            'guardian_whatsapp_number', 'guardian_relationship',
        ] as $key) {
            $value = trim((string) $this->input($key));
            $this->merge([$key => $value === '' ? null : $value]);
        }
        $this->merge([
            'first_name' => trim((string) $this->input('first_name')),
            'last_name' => trim((string) $this->input('last_name')),
            'email' => mb_strtolower(trim((string) $this->input('email'))),
            'country' => trim((string) $this->input('country')) ?: null,
            'application_intake_id' => $this->input('application_intake_id') ?: null,
            'preferred_branch_id' => $this->input('preferred_branch_id') ?: null,
            'custom_fields' => is_array($this->input('custom_fields')) ? $this->input('custom_fields') : [],
        ]);
    }

    public function authorize(): bool
    {
        return $this->academy() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $academy = $this->academyOrFail();
        $collectGuardian = $academy->collect_guardian_information;
        $forAcademy = fn ($query) => $query->where('academy_id', $academy->id);
        $rules = [
            'first_name' => ['required', 'string', 'max:100'], 'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'], 'email' => ['required', 'email:rfc', 'max:254'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'gender' => ['nullable', Rule::in(['Male', 'Female'])], 'phone' => ['nullable', 'string', 'max:32'],
            'whatsapp_number' => ['nullable', 'string', 'max:32'], 'home_address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'], 'state_region' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', Rule::in(config('countries'))],
            'guardian_first_name' => $collectGuardian ? ['nullable', 'string', 'max:100'] : ['prohibited'],
            'guardian_last_name' => $collectGuardian ? ['required_with:guardian_first_name', 'nullable', 'string', 'max:100'] : ['prohibited'],
            'guardian_email' => $collectGuardian ? ['nullable', 'email:rfc', 'max:254'] : ['prohibited'],
            'guardian_phone' => $collectGuardian ? ['required_with:guardian_first_name', 'nullable', 'string', 'max:32'] : ['prohibited'],
            'guardian_whatsapp_number' => $collectGuardian ? ['nullable', 'string', 'max:32'] : ['prohibited'],
            'guardian_relationship' => $collectGuardian ? ['nullable', 'string', 'max:80'] : ['prohibited'],
            'guardian_signature' => $collectGuardian ? ['nullable', File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max(2 * 1024)] : ['prohibited'],
            'application_intake_id' => ['required', 'integer', Rule::exists('application_intakes', 'id')->where($forAcademy)],
            'preferred_branch_id' => [
                $academy->branches()->where('is_active', true)->exists() ? 'required' : 'nullable',
                'integer', Rule::exists('branches', 'id')->where(fn ($query) => $query
                    ->where('academy_id', $academy->id)->where('is_active', true)),
            ],
            'consent_accepted' => ['required', 'accepted'],
            'custom_fields' => ['array'],
        ];
        foreach ($this->fields() as $field) {
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
                $validator->errors()->add("custom_fields.{$key}", 'This field is not available for this academy.');
            }
            $academy = $this->academyOrFail();
            $intake = app(ApplicationIntakeAvailability::class)->query($academy)
                ->whereKey($this->input('application_intake_id'))->first();
            if ($intake === null) {
                $validator->errors()->add('application_intake_id', 'This application option is no longer accepting applications.');
            } elseif ($intake->branch_id !== null && $intake->branch_id !== $this->integer('preferred_branch_id')) {
                $validator->errors()->add('preferred_branch_id', 'The selected location is not available for this cohort.');
            }
        });
    }

    public function academyOrFail(): Academy
    {
        return $this->academy() ?? abort(404);
    }

    private function academy(): ?Academy
    {
        return AcademyWebsite::query()->where('subdomain', (string) $this->route('subdomain'))
            ->where('is_published', true)->with('academy')->first()?->academy;
    }

    /** @return Collection<int, AcademyCustomField> */
    private function fields(): Collection
    {
        return $this->academyOrFail()->customFields()->where('entity_type', 'player')->where('is_active', true)->get();
    }
}
