<?php

namespace App\Http\Requests\Academies;

use App\Academies\AcademyContext;
use App\Enums\CustomFieldType;
use App\Enums\PlayerStatus;
use App\Models\AcademyCustomField;
use App\Models\Player;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SavePlayerRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $identifier = mb_strtoupper(trim((string) $this->input('internal_identifier')));
        $newGuardian = $this->input('new_guardian', []);
        if (is_array($newGuardian)) {
            $newGuardian = array_map(
                fn (mixed $value): string => trim((string) $value),
                $newGuardian,
            );
            if (($newGuardian['first_name'] ?? '') === '') {
                $newGuardian = [];
            }
        }
        $this->merge([
            'internal_identifier' => $identifier === '' ? null : $identifier,
            'first_name' => trim((string) $this->input('first_name')),
            'last_name' => trim((string) $this->input('last_name')),
            'middle_name' => $this->nullableString('middle_name'),
            'gender' => $this->nullableString('gender'),
            'phone' => $this->nullableString('phone'),
            'whatsapp_number' => $this->nullableString('whatsapp_number'),
            'home_address' => $this->nullableString('home_address'),
            'city' => $this->nullableString('city'),
            'state_region' => $this->nullableString('state_region'),
            'country' => $this->nullableString('country'),
            'guardian_ids' => is_array($this->input('guardian_ids')) ? $this->input('guardian_ids') : [],
            'guardian_relationships' => is_array($this->input('guardian_relationships')) ? $this->input('guardian_relationships') : [],
            'custom_fields' => is_array($this->input('custom_fields')) ? $this->input('custom_fields') : [],
            'new_guardian' => $newGuardian,
        ]);
    }

    public function authorize(): bool
    {
        $context = app(AcademyContext::class);
        $player = $this->route('player');

        if ($player instanceof Player) {
            abort_unless($player->academy_id === $context->id(), 404);
        }

        return $context->canManageAcademy();
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $context = app(AcademyContext::class);
        $player = $this->route('player');
        $playerId = $player instanceof Player ? $player->id : null;
        $forAcademy = fn (Builder $query): Builder => $query->where('academy_id', $context->id());

        $rules = [
            'internal_identifier' => ['nullable', 'string', 'max:64', Rule::unique('players')->where($forAcademy)->ignore($playerId)],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'gender' => ['nullable', 'string', 'max:32'],
            'phone' => ['nullable', 'string', 'max:32'],
            'whatsapp_number' => ['nullable', 'string', 'max:32'],
            'home_address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'state_region' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::enum(PlayerStatus::class)],
            'guardian_ids' => ['array'],
            'guardian_ids.*' => ['integer', 'distinct', Rule::exists('guardians', 'id')->where($forAcademy)],
            'guardian_relationships' => ['array'],
            'guardian_relationships.*' => ['nullable', 'string', 'max:80'],
            'new_guardian' => ['array'],
            'new_guardian.first_name' => ['nullable', 'string', 'max:100'],
            'new_guardian.last_name' => ['required_with:new_guardian.first_name', 'string', 'max:100'],
            'new_guardian.phone' => ['required_with:new_guardian.first_name', 'string', 'max:32'],
            'new_guardian.whatsapp_number' => ['nullable', 'string', 'max:32'],
            'new_guardian.email' => ['nullable', 'email:rfc', 'max:254'],
            'new_guardian.relationship' => ['nullable', 'string', 'max:80'],
        ];

        $fields = AcademyCustomField::query()
            ->where('academy_id', $context->id())
            ->where('entity_type', 'player')
            ->where('is_active', true)
            ->get();
        $keys = $fields->pluck('key')->all();
        $rules['custom_fields'] = ['array'.($keys === [] ? '' : ':'.implode(',', $keys))];

        foreach ($fields as $field) {
            $fieldRules = [$field->is_required ? 'required' : 'nullable'];
            $fieldRules = [...$fieldRules, ...$this->rulesForCustomField($field)];
            $rules["custom_fields.{$field->key}"] = $fieldRules;
        }

        return $rules;
    }

    /** @return array<int, mixed> */
    private function rulesForCustomField(AcademyCustomField $field): array
    {
        return match ($field->type) {
            CustomFieldType::Text => ['string', 'max:500'],
            CustomFieldType::Textarea => ['string', 'max:5000'],
            CustomFieldType::Number => ['numeric'],
            CustomFieldType::Date => ['date'],
            CustomFieldType::Boolean => ['boolean'],
            CustomFieldType::Select => [Rule::in($field->options ?? [])],
        };
    }

    private function nullableString(string $key): ?string
    {
        $value = trim((string) $this->input($key));

        return $value === '' ? null : $value;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $submitted = array_keys($this->input('custom_fields', []));
            $allowed = AcademyCustomField::query()
                ->where('academy_id', app(AcademyContext::class)->id())
                ->where('entity_type', 'player')
                ->where('is_active', true)
                ->pluck('key')->all();

            foreach (array_diff($submitted, $allowed) as $unknownKey) {
                $validator->errors()->add("custom_fields.$unknownKey", 'This custom field is not available for this academy.');
            }
        });
    }
}
