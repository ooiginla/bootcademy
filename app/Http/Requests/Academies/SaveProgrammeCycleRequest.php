<?php

namespace App\Http\Requests\Academies;

use App\Academies\AcademyContext;
use App\Models\AcademyCycle;
use App\Models\ProgrammeCycle;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveProgrammeCycleRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'fee_name' => trim((string) $this->input('fee_name', 'Programme fee')),
            'application_fee' => trim((string) $this->input('application_fee', '0')),
            'is_active' => $this->boolean('is_active'),
            'branch_id' => $this->input('branch_id') ?: null,
        ]);
    }

    public function authorize(): bool
    {
        $context = app(AcademyContext::class);
        $cycle = $this->route('cycle');
        $offering = $this->route('programmeCycle');
        abort_unless($cycle instanceof AcademyCycle && $cycle->academy_id === $context->id(), 404);
        if ($offering instanceof ProgrammeCycle) {
            abort_unless($offering->academy_id === $context->id() && $offering->academy_cycle_id === $cycle->id, 404);
        }

        return $context->canManageFinance();
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        /** @var AcademyCycle $cycle */
        $cycle = $this->route('cycle');
        $offering = $this->route('programmeCycle');
        $academyId = app(AcademyContext::class)->id();
        $forAcademy = fn (Builder $query): Builder => $query->where('academy_id', $academyId);

        return [
            'programme_id' => [
                'required', 'integer', Rule::exists('programmes', 'id')->withoutTrashed()->where($forAcademy),
            ],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->withoutTrashed()->where($forAcademy)],
            'fee_name' => ['required', 'string', 'max:160'],
            'application_fee' => ['required', 'regex:/^\d{1,12}(?:\.\d{1,2})?$/'],
            'is_active' => ['boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var AcademyCycle $cycle */
            $cycle = $this->route('cycle');
            $offering = $this->route('programmeCycle');
            $duplicateQuery = ProgrammeCycle::query()
                ->where('academy_id', app(AcademyContext::class)->id())
                ->where('academy_cycle_id', $cycle->id)
                ->where('programme_id', $this->input('programme_id'))
                ->where('branch_scope_id', (int) ($this->input('branch_id') ?: 0));
            if ($offering instanceof ProgrammeCycle) {
                $duplicateQuery->where('id', '<>', $offering->id);
            }
            if ($duplicateQuery->exists()) {
                $validator->errors()->add('programme_id', 'Fees already exist for this programme and branch scope in this cycle.');
            }
        });
    }
}
