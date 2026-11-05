<?php

namespace App\Http\Requests\Academies;

use App\Academies\AcademyContext;
use App\Enums\CustomFieldType;
use App\Models\AcademyCustomField;
use App\Models\Application;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Validator;

class SaveApplicationRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        foreach ([
            'internal_identifier', 'middle_name', 'gender', 'phone', 'whatsapp_number', 'home_address', 'city', 'state_region', 'country',
            'guardian_first_name', 'guardian_last_name', 'guardian_email', 'guardian_phone',
            'guardian_whatsapp_number', 'guardian_relationship',
        ] as $key) {
            $value = trim((string) $this->input($key));
            $this->merge([$key => $value === '' ? null : $value]);
        }
        $this->merge([
            'first_name' => trim((string) $this->input('first_name')),
            'last_name' => trim((string) $this->input('last_name')),
            'internal_identifier' => $this->input('internal_identifier') === null
                ? null
                : mb_strtoupper(trim((string) $this->input('internal_identifier'))),
            'programme_id' => $this->input('programme_id') ?: null,
            'programme_cycle_id' => $this->input('programme_cycle_id') ?: null,
            'branch_id' => $this->input('branch_id') ?: null,
            'custom_fields' => is_array($this->input('custom_fields')) ? $this->input('custom_fields') : [],
        ]);
    }

    public function authorize(): bool
    {
        $context = app(AcademyContext::class);
        $application = $this->route('application');
        if ($application instanceof Application) {
            abort_unless($application->academy_id === $context->id(), 404);
        }

        return $context->canManageAcademy();
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $academyId = app(AcademyContext::class)->id();
        $application = $this->route('application');
        $applicationId = $application instanceof Application ? $application->id : null;
        $forAcademy = fn (Builder $query): Builder => $query->where('academy_id', $academyId);
        $rules = [
            'internal_identifier' => [
                'nullable', 'string', 'max:64',
                Rule::unique('applications')->where($forAcademy)->ignore($applicationId),
                Rule::unique('players')->where($forAcademy),
            ],
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
        $fields = AcademyCustomField::query()->where('academy_id', $academyId)->where('entity_type', 'player')->where('is_active', true)->get();
        $rules['custom_fields'] = ['array'];
        foreach ($fields as $field) {
            $fieldRules = [$field->is_required ? 'required' : 'nullable'];
            $rules["custom_fields.{$field->key}"] = [...$fieldRules, ...match ($field->type) {
                CustomFieldType::Text => ['string', 'max:500'], CustomFieldType::Textarea => ['string', 'max:5000'],
                CustomFieldType::Number => ['numeric'], CustomFieldType::Date => ['date'], CustomFieldType::Boolean => ['boolean'],
                CustomFieldType::Select => [Rule::in($field->options ?? [])],
            }];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $allowed = AcademyCustomField::query()->where('academy_id', app(AcademyContext::class)->id())->where('entity_type', 'player')->where('is_active', true)->pluck('key')->all();
            foreach (array_diff(array_keys($this->input('custom_fields', [])), $allowed) as $key) {
                $validator->errors()->add("custom_fields.$key", 'This custom field is not available for this academy.');
            }
            $application = $this->route('application');
            if (! $application instanceof Application) {
                $application = null;
            }
            if ($application?->converted_player_id !== null) {
                $validator->errors()->add('application', 'An enrolled application must be updated from the player profile.');
            }
            $newProgrammeCycleId = $this->input('programme_cycle_id');
            $currentProgrammeCycleId = $application instanceof Application ? $application->programme_cycle_id : null;
            if ($application !== null
                && (string) ($newProgrammeCycleId ?? '') !== (string) ($currentProgrammeCycleId ?? '')
                && $application->invoices()->exists()) {
                $validator->errors()->add('programme_cycle_id', 'The programme offering cannot change after an invoice has been issued.');
            }
            if ($application !== null
                && (string) ($this->input('branch_id') ?? '') !== (string) ($application->branch_id ?? '')
                && $application->invoices()->exists()) {
                $validator->errors()->add('branch_id', 'The branch cannot change after an invoice has been issued.');
            }
            $academy = app(AcademyContext::class)->academy();
            $hasActiveOfferings = $academy->programmeCycles()->where('is_active', true)
                ->whereHas('cycle', fn ($query) => $query->where('status', 'active'))->exists();
            if ($newProgrammeCycleId === null && $hasActiveOfferings) {
                $validator->errors()->add('programme_cycle_id', 'Select an active cycle and programme offering.');
            }
            if ($newProgrammeCycleId !== null) {
                $isCurrent = (string) $newProgrammeCycleId === (string) ($currentProgrammeCycleId ?? '');
                $isActive = $academy->programmeCycles()->whereKey($newProgrammeCycleId)->where('is_active', true)
                    ->whereHas('cycle', fn ($query) => $query->where('status', 'active'))->exists();
                if (! $isCurrent && ! $isActive) {
                    $validator->errors()->add('programme_cycle_id', 'Select an offering from an active cycle.');
                }
            }
        });
    }
}
