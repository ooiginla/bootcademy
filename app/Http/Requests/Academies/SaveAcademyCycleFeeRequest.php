<?php

namespace App\Http\Requests\Academies;

use App\Academies\AcademyContext;
use App\Models\AcademyCycle;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveAcademyCycleFeeRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')), 'category' => trim((string) $this->input('category')),
            'is_compulsory' => $this->boolean('is_compulsory'), 'applies_to_all_programmes' => $this->boolean('applies_to_all_programmes'),
            'is_active' => $this->boolean('is_active'), 'programme_ids' => is_array($this->input('programme_ids')) ? $this->input('programme_ids') : [],
        ]);
    }

    public function authorize(): bool
    {
        $cycle = $this->route('cycle');
        $context = app(AcademyContext::class);
        abort_unless($cycle instanceof AcademyCycle && $cycle->academy_id === $context->id(), 404);

        return $context->canManageFinance();
    }

    public function rules(): array
    {
        $academyId = app(AcademyContext::class)->id();
        $forAcademy = fn (Builder $query): Builder => $query->where('academy_id', $academyId);

        return [
            'name' => ['required', 'string', 'max:160'], 'category' => ['required', 'string', 'max:80'],
            'amount' => ['required', 'regex:/^\d{1,12}(?:\.\d{1,2})?$/', 'not_in:0,0.00'],
            'is_compulsory' => ['boolean'], 'applies_to_all_programmes' => ['boolean'], 'is_active' => ['boolean'],
            'programme_ids' => ['exclude_if:applies_to_all_programmes,true', 'required_if:applies_to_all_programmes,false', 'array', 'min:1'],
            'programme_ids.*' => ['integer', 'distinct', Rule::exists('programmes', 'id')->where($forAcademy)],
        ];
    }
}
