<?php

namespace App\Http\Requests\Academies;

use App\Academies\AcademyContext;
use App\Enums\AcademyCycleType;
use App\Models\AcademyCycle;
use App\Models\AcademyCycleTerm;
use App\Models\Cohort;
use App\Models\ProgrammeCycle;
use App\Models\ProgrammeCyclePaymentPlan;
use App\Support\Money;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveProgrammeCyclePaymentPlanRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'description' => trim((string) $this->input('description')) ?: null,
            'sort_order' => $this->integer('sort_order', 100),
            'academy_cycle_term_id' => $this->input('academy_cycle_term_id') ?: null,
            'cohort_id' => $this->input('cohort_id') ?: null,
            'is_default' => $this->boolean('is_default'),
            'is_active' => $this->boolean('is_active', true),
            'is_public' => $this->boolean('is_public', true),
            'fee_items' => is_array($this->input('fee_items')) ? $this->input('fee_items') : [],
            'installments' => is_array($this->input('installments')) ? $this->input('installments') : [],
        ]);
    }

    public function authorize(): bool
    {
        $context = app(AcademyContext::class);
        $cycle = $this->route('cycle');
        $offering = $this->route('programmeCycle');
        $plan = $this->route('paymentPlan');
        abort_unless($cycle instanceof AcademyCycle && $cycle->academy_id === $context->id(), 404);
        abort_unless($offering instanceof ProgrammeCycle
            && $offering->academy_id === $context->id()
            && $offering->academy_cycle_id === $cycle->id, 404);
        if ($plan instanceof ProgrammeCyclePaymentPlan) {
            abort_unless($plan->academy_id === $context->id() && $plan->programme_cycle_id === $offering->id, 404);
        }

        return $context->canManageFinance();
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $academyId = app(AcademyContext::class)->id();
        $offering = $this->route('programmeCycle');
        $plan = $this->route('paymentPlan');
        abort_unless($offering instanceof ProgrammeCycle, 404);
        $planId = $plan instanceof ProgrammeCyclePaymentPlan ? $plan->id : null;
        $termScopeId = (int) ($this->input('academy_cycle_term_id') ?: 0);
        $cohortScopeId = (int) ($this->input('cohort_id') ?: 0);
        $forAcademy = fn (Builder $query): Builder => $query->where('academy_id', $academyId);

        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('programme_cycle_payment_plans', 'name')->withoutTrashed()
                ->where(fn (Builder $query): Builder => $query
                    ->where('academy_id', $academyId)
                    ->where('programme_cycle_id', $offering->id)
                    ->where('term_scope_id', $termScopeId)
                    ->where('cohort_scope_id', $cohortScopeId))
                ->ignore($planId)],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            'academy_cycle_term_id' => ['nullable', 'integer', Rule::exists('academy_cycle_terms', 'id')->where($forAcademy)],
            'cohort_id' => ['nullable', 'integer', Rule::exists('cohorts', 'id')->where($forAcademy)],
            'is_default' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'is_public' => ['required', 'boolean'],
            'fee_items' => ['required', 'array', 'min:1', 'max:30'],
            'fee_items.*.name' => ['required', 'string', 'max:120'],
            'fee_items.*.description' => ['nullable', 'string', 'max:500'],
            'fee_items.*.amount' => ['required', 'regex:/^\d{1,12}(?:\.\d{1,2})?$/', 'not_in:0,0.00'],
            'installments' => ['required', 'array', 'min:1', 'max:24'],
            'installments.*.name' => ['required', 'string', 'max:120'],
            'installments.*.amount' => ['required', 'regex:/^\d{1,12}(?:\.\d{1,2})?$/', 'not_in:0,0.00'],
            'installments.*.due_on' => ['nullable', 'date'],
            'installments.*.due_offset_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var AcademyCycle $cycle */
            $cycle = $this->route('cycle');
            /** @var ProgrammeCycle $offering */
            $offering = $this->route('programmeCycle');
            $term = null;
            if ($this->input('academy_cycle_term_id')) {
                $term = AcademyCycleTerm::query()->where('academy_id', $cycle->academy_id)
                    ->where('academy_cycle_id', $cycle->id)
                    ->whereKey($this->input('academy_cycle_term_id'))->first();
                if ($term === null || ! $cycle->supportsTerms()) {
                    $validator->errors()->add('academy_cycle_term_id', 'Select a term from this fixed cycle.');
                }
            }
            if ($this->input('cohort_id')) {
                $cohort = Cohort::query()->where('academy_id', $cycle->academy_id)
                    ->where('programme_cycle_id', $offering->id)->whereKey($this->input('cohort_id'))->first();
                if ($cohort === null) {
                    $validator->errors()->add('cohort_id', 'Select a cohort from this programme fee.');
                }
            }
            $plan = $this->route('paymentPlan');
            if ($plan instanceof ProgrammeCyclePaymentPlan
                && ($plan->academy_cycle_term_id !== ($this->input('academy_cycle_term_id') ? (int) $this->input('academy_cycle_term_id') : null)
                    || $plan->cohort_id !== ($this->input('cohort_id') ? (int) $this->input('cohort_id') : null))) {
                $validator->errors()->add('name', 'A payment plan cannot be moved to another fee scope.');
            }
            if ($this->boolean('is_default') && ! $this->boolean('is_active')) {
                $validator->errors()->add('is_active', 'The default payment plan must remain active.');
            }

            $feeTotal = $this->sumAmounts($this->input('fee_items', []));
            $installmentTotal = $this->sumAmounts($this->input('installments', []));
            if ($feeTotal !== $installmentTotal) {
                $validator->errors()->add('installments', 'Repayments must add up exactly to the payment plan total.');
            }

            $rangeStart = $term instanceof AcademyCycleTerm ? $term->starts_on : $cycle->starts_on;
            $rangeEnd = $term instanceof AcademyCycleTerm ? $term->ends_on : $cycle->ends_on;
            foreach ($this->input('installments', []) as $index => $installment) {
                if (! is_array($installment)) {
                    continue;
                }
                if ($cycle->type === AcademyCycleType::Fixed) {
                    if (empty($installment['due_on'])) {
                        $validator->errors()->add("installments.{$index}.due_on", 'Enter a due date for each repayment.');
                    } elseif ($rangeStart && $rangeEnd) {
                        $dueOn = Carbon::parse($installment['due_on']);
                        if ($dueOn->lt($rangeStart) || $dueOn->gt($rangeEnd)) {
                            $validator->errors()->add("installments.{$index}.due_on", 'The due date must fall within this fee period.');
                        }
                    }
                } elseif (data_get($installment, 'due_offset_days') === null || data_get($installment, 'due_offset_days') === '') {
                    $validator->errors()->add("installments.{$index}.due_offset_days", 'Enter the number of days after activation.');
                } elseif ((int) $installment['due_offset_days'] > $cycle->maximumRollingDays()) {
                    $validator->errors()->add("installments.{$index}.due_offset_days", 'The repayment must fall within the rolling cycle duration.');
                }
            }
        });
    }

    /** @param array<mixed> $items */
    private function sumAmounts(array $items): int
    {
        return collect($items)->sum(fn ($item): int => is_array($item) && isset($item['amount'])
            ? Money::toMinor((string) $item['amount'])
            : 0);
    }
}
