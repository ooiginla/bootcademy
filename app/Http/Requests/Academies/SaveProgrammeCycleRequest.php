<?php

namespace App\Http\Requests\Academies;

use App\Academies\AcademyContext;
use App\Enums\AcademyCycleType;
use App\Enums\ProgrammeCyclePaymentMode;
use App\Models\AcademyCycle;
use App\Models\Cohort;
use App\Models\ProgrammeCycle;
use App\Support\Money;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
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
            'fee_items' => is_array($this->input('fee_items')) ? $this->input('fee_items') : [],
            'installments' => is_array($this->input('installments')) ? $this->input('installments') : [],
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
        $fixed = $cycle->type === AcademyCycleType::Fixed;

        return [
            'programme_id' => [
                'required', 'integer', Rule::exists('programmes', 'id')->where($forAcademy),
            ],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where($forAcademy)],
            'fee_name' => ['required', 'string', 'max:160'],
            'application_fee' => ['required', 'regex:/^\d{1,12}(?:\.\d{1,2})?$/'],
            'payment_mode' => ['required', Rule::enum(ProgrammeCyclePaymentMode::class)],
            'is_active' => ['boolean'],
            'fee_items' => ['required', 'array', 'min:1', 'max:20'],
            'fee_items.*.name' => ['required', 'string', 'max:120'],
            'fee_items.*.amount' => ['required', 'regex:/^\d{1,12}(?:\.\d{1,2})?$/', 'not_in:0,0.00'],
            'fee_items.*.academy_cycle_term_id' => ['nullable', 'integer', Rule::exists('academy_cycle_terms', 'id')->where($forAcademy)],
            'fee_items.*.cohort_id' => ['nullable', 'integer', Rule::exists('cohorts', 'id')->where($forAcademy)],
            'installments' => ['exclude_unless:payment_mode,installments', 'required_if:payment_mode,installments', 'array', 'min:2', 'max:24'],
            'installments.*.name' => ['required_if:payment_mode,installments', 'string', 'max:120'],
            'installments.*.amount' => ['required_if:payment_mode,installments', 'regex:/^\d{1,12}(?:\.\d{1,2})?$/', 'not_in:0,0.00'],
            'installments.*.due_on' => [$fixed ? 'required_if:payment_mode,installments' : 'nullable', 'nullable', 'date'],
            'installments.*.due_offset_days' => [$fixed ? 'nullable' : 'required_if:payment_mode,installments', 'nullable', 'integer', 'min:0', 'max:3650'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var AcademyCycle $cycle */
            $cycle = $this->route('cycle');
            $offering = $this->route('programmeCycle');
            foreach ($this->input('fee_items', []) as $index => $item) {
                if (! is_array($item)) {
                    continue;
                }
                $termId = $item['academy_cycle_term_id'] ?? null;
                if ($termId && ! $cycle->terms()->whereKey($termId)->exists()) {
                    $validator->errors()->add("fee_items.{$index}.academy_cycle_term_id", 'Select a term from this billing cycle.');
                }
                $cohortId = $item['cohort_id'] ?? null;
                if ($cohortId) {
                    $cohort = Cohort::query()->whereKey($cohortId)->first();
                    if (! $offering instanceof ProgrammeCycle || $cohort?->programme_cycle_id !== $offering->id) {
                        $validator->errors()->add("fee_items.{$index}.cohort_id", 'The cohort must belong to this programme offering. Save the programme offering before adding a cohort-specific fee.');
                    }
                }
            }
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
            if ($validator->errors()->isNotEmpty() || $this->input('payment_mode') !== ProgrammeCyclePaymentMode::Installments->value) {
                return;
            }
            $feeItems = $this->input('fee_items', []);
            $installments = $this->input('installments', []);
            if (! is_array($feeItems) || ! is_array($installments)) {
                return;
            }
            $feeTotal = $this->sumAmounts($feeItems);
            $installmentTotal = $this->sumAmounts($installments);
            if ($feeTotal !== $installmentTotal) {
                $validator->errors()->add('installments', 'Installments must add up exactly to the programme fee total.');
            }
            foreach ($installments as $index => $installment) {
                if (! is_array($installment)) {
                    continue;
                }
                if ($cycle->type === AcademyCycleType::Fixed && isset($installment['due_on'])) {
                    $dueOn = Carbon::parse($installment['due_on']);
                    if ($dueOn->lt($cycle->starts_on) || $dueOn->gt($cycle->ends_on)) {
                        $validator->errors()->add("installments.{$index}.due_on", 'Installment due dates must fall inside the fixed cycle.');
                    }
                }
                if ($cycle->type === AcademyCycleType::Rolling && isset($installment['due_offset_days'])
                    && (int) $installment['due_offset_days'] > $cycle->maximumRollingDays()) {
                    $validator->errors()->add("installments.{$index}.due_offset_days", 'The due offset must fall inside the rolling duration.');
                }
            }
        });
    }

    /** @param array<mixed> $items */
    private function sumAmounts(array $items): int
    {
        $total = 0;
        foreach ($items as $item) {
            if (is_array($item) && isset($item['amount']) && is_scalar($item['amount'])) {
                $total += Money::toMinor((string) $item['amount']);
            }
        }

        return $total;
    }
}
