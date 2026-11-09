<?php

namespace App\Http\Requests\Academies;

use App\Academies\AcademyContext;
use App\Models\AcademyCycle;
use App\Models\AcademyCycleTerm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveAcademyCycleTermRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'starts_on' => $this->input('starts_on') ?: null,
            'ends_on' => $this->input('ends_on') ?: null,
            'sort_order' => $this->integer('sort_order', 1),
            'is_default' => $this->boolean('is_default'),
            'is_active' => $this->boolean('is_active', true),
        ]);
    }

    public function authorize(): bool
    {
        $context = app(AcademyContext::class);
        $cycle = $this->route('cycle');
        $term = $this->route('term');
        abort_unless($cycle instanceof AcademyCycle && $cycle->academy_id === $context->id(), 404);
        if ($term instanceof AcademyCycleTerm) {
            abort_unless($term->academy_id === $context->id() && $term->academy_cycle_id === $cycle->id, 404);
        }

        return $context->canManageFinance();
    }

    public function rules(): array
    {
        /** @var AcademyCycle $cycle */
        $cycle = $this->route('cycle');
        $term = $this->route('term');

        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('academy_cycle_terms', 'name')
                ->where('academy_id', app(AcademyContext::class)->id())->where('academy_cycle_id', $cycle->id)->ignore($term?->id)],
            'starts_on' => ['nullable', 'date', 'required_with:ends_on'],
            'ends_on' => ['nullable', 'date', 'required_with:starts_on', 'after_or_equal:starts_on'],
            'sort_order' => ['required', 'integer', 'between:1,1000'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var AcademyCycle $cycle */
            $cycle = $this->route('cycle');
            if ($cycle->type->value !== 'fixed' || ! $this->input('starts_on') || ! $this->input('ends_on')) {
                return;
            }
            if ($this->date('starts_on')->lt($cycle->starts_on) || $this->date('ends_on')->gt($cycle->ends_on)) {
                $validator->errors()->add('starts_on', 'Term dates must fall within the billing cycle dates.');
            }
        });
    }
}
