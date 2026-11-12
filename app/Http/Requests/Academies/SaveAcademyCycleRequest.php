<?php

namespace App\Http\Requests\Academies;

use App\Academies\AcademyContext;
use App\Enums\AcademyCycleDurationUnit;
use App\Enums\AcademyCycleStatus;
use App\Enums\AcademyCycleType;
use App\Models\AcademyCycle;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveAcademyCycleRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'starts_on' => $this->input('starts_on') ?: null,
            'ends_on' => $this->input('ends_on') ?: null,
            'duration_value' => $this->input('duration_value') ?: null,
            'duration_unit' => $this->input('duration_unit') ?: null,
        ]);
    }

    public function authorize(): bool
    {
        $context = app(AcademyContext::class);
        $cycle = $this->route('cycle');
        if ($cycle instanceof AcademyCycle) {
            abort_unless($cycle->academy_id === $context->id(), 404);
        }

        return $context->canManageFinance();
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $context = app(AcademyContext::class);
        $cycle = $this->route('cycle');
        $cycleId = $cycle instanceof AcademyCycle ? $cycle->id : null;
        $forAcademy = fn (Builder $query): Builder => $query->where('academy_id', $context->id());
        $durationMaximum = match ($this->input('duration_unit')) {
            AcademyCycleDurationUnit::Days->value => 1825,
            AcademyCycleDurationUnit::Weeks->value => 260,
            AcademyCycleDurationUnit::Months->value => 60,
            default => 3650,
        };

        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('academy_cycles')->withoutTrashed()->where($forAcademy)->ignore($cycleId)],
            'type' => ['required', Rule::enum(AcademyCycleType::class)],
            'starts_on' => ['nullable', 'required_if:type,fixed', 'date'],
            'ends_on' => ['nullable', 'required_if:type,fixed', 'date', 'after:starts_on'],
            'duration_value' => ['nullable', 'required_if:type,rolling', 'integer', 'min:1', "max:{$durationMaximum}"],
            'duration_unit' => ['nullable', 'required_if:type,rolling', Rule::enum(AcademyCycleDurationUnit::class)],
            'status' => ['required', Rule::enum(AcademyCycleStatus::class)],
        ];
    }
}
