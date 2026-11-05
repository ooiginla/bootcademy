<?php

namespace App\Http\Requests\Academies;

use App\Academies\AcademyContext;
use App\Enums\CustomFieldType;
use App\Models\AcademyCustomField;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveCustomFieldRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $entityType = $this->routeIs('academy.coach-fields.*') ? 'coach' : 'player';
        $rawOptions = $this->input('options', []);
        if (is_string($rawOptions)) {
            $rawOptions = explode(',', $rawOptions);
        }

        $options = is_array($rawOptions)
            ? array_values(array_unique(array_filter(array_map(
                fn (mixed $option): string => trim((string) $option),
                $rawOptions,
            ))))
            : [];

        $this->merge([
            'entity_type' => $entityType,
            'key' => Str::of((string) $this->input('key'))->trim()->lower()->replace('-', '_')->value(),
            'label' => trim((string) $this->input('label')),
            'options' => $options,
        ]);
    }

    public function authorize(): bool
    {
        $context = app(AcademyContext::class);
        $field = $this->route('customField');
        if ($field instanceof AcademyCustomField) {
            $entityType = $this->routeIs('academy.coach-fields.*') ? 'coach' : 'player';
            abort_unless($field->academy_id === $context->id() && $field->entity_type === $entityType, 404);
        }

        return $context->canManageAcademy();
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $context = app(AcademyContext::class);
        $field = $this->route('customField');
        $fieldId = $field instanceof AcademyCustomField ? $field->id : null;
        $entityType = $this->routeIs('academy.coach-fields.*') ? 'coach' : 'player';
        $forAcademy = fn (Builder $query): Builder => $query
            ->where('academy_id', $context->id())
            ->where('entity_type', $entityType);

        return [
            'entity_type' => ['required', Rule::in(['player', 'coach'])],
            'key' => ['required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('academy_custom_fields')->where($forAcademy)->ignore($fieldId)],
            'label' => ['required', 'string', 'max:160'],
            'type' => ['required', Rule::enum(CustomFieldType::class)],
            'options' => ['array', 'max:100'],
            'options.*' => ['string', 'max:160', 'distinct'],
            'is_required' => ['required', 'boolean'],
            'is_sensitive' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('type') === CustomFieldType::Select->value && count($this->input('options', [])) === 0) {
                $validator->errors()->add('options', 'Add at least one option for a select field.');
            }
        });
    }
}
