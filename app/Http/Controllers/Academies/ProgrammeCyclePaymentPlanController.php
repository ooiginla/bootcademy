<?php

namespace App\Http\Controllers\Academies;

use App\Academies\AcademyContext;
use App\Academies\Actions\SaveProgrammeCyclePaymentPlan;
use App\Http\Controllers\Controller;
use App\Http\Requests\Academies\SaveProgrammeCyclePaymentPlanRequest;
use App\Models\AcademyCycle;
use App\Models\ProgrammeCycle;
use App\Models\ProgrammeCycleFeeItem;
use App\Models\ProgrammeCyclePaymentPlan;
use App\Models\ProgrammeCyclePaymentPlanInstallment;
use App\Models\ProgrammeCyclePaymentPlanItem;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProgrammeCyclePaymentPlanController extends Controller
{
    public function show(
        Request $request,
        AcademyCycle $cycle,
        ProgrammeCycle $programmeCycle,
        AcademyContext $context,
    ): Response {
        abort_unless($context->canManageFinance(), 403);
        abort_unless($cycle->academy_id === $context->id()
            && $programmeCycle->academy_id === $context->id()
            && $programmeCycle->academy_cycle_id === $cycle->id, 404);

        $termId = $request->integer('term_id') ?: null;
        $cohortId = $request->integer('cohort_id') ?: null;
        $term = $termId ? $cycle->terms()->whereKey($termId)->firstOrFail() : null;
        $cohort = $cohortId ? $programmeCycle->cohorts()->whereKey($cohortId)->firstOrFail() : null;
        $baseItems = $programmeCycle->feeItems()
            ->where('academy_cycle_term_id', $termId)
            ->where('cohort_id', $cohortId)
            ->orderBy('sort_order')->get();
        abort_if($baseItems->isEmpty(), 404);

        $programmeCycle->load(['programme', 'branch']);
        $plans = $programmeCycle->paymentPlans()
            ->where('term_scope_id', (int) ($termId ?? 0))
            ->where('cohort_scope_id', (int) ($cohortId ?? 0))
            ->with(['items', 'installments'])
            ->orderByDesc('is_default')->orderBy('name')->get();

        return Inertia::render('academies/programme-fee-details', [
            'offering' => [
                'id' => $programmeCycle->id,
                'fee_name' => $programmeCycle->fee_name,
                'programme_name' => $programmeCycle->programme->name,
                'branch_name' => $programmeCycle->branch?->name,
                'currency' => $programmeCycle->currency,
                'is_active' => $programmeCycle->is_active,
            ],
            'cycle' => [
                'id' => $cycle->id, 'name' => $cycle->name, 'type' => $cycle->type->value,
                'starts_on' => $cycle->starts_on?->toDateString(), 'ends_on' => $cycle->ends_on?->toDateString(),
                'duration_value' => $cycle->duration_value, 'duration_unit' => $cycle->duration_unit?->value,
            ],
            'scope' => [
                'academy_cycle_term_id' => $term?->id,
                'term_name' => $term?->name,
                'starts_on' => $term?->starts_on?->toDateString(),
                'ends_on' => $term?->ends_on?->toDateString(),
                'cohort_id' => $cohort?->id,
                'cohort_name' => $cohort?->name,
            ],
            'base_fee_items' => $baseItems->map(fn (ProgrammeCycleFeeItem $item): array => [
                'name' => $item->name, 'amount' => Money::fromMinor((int) $item->amount_minor),
            ])->values(),
            'plans' => $plans->map(fn (ProgrammeCyclePaymentPlan $plan): array => [
                'id' => $plan->id, 'name' => $plan->name, 'description' => $plan->description,
                'currency' => $plan->currency, 'total' => Money::fromMinor($plan->total_minor),
                'is_default' => $plan->is_default, 'is_active' => $plan->is_active,
                'is_legacy' => $plan->is_legacy,
                'items' => $plan->items->sortBy('sort_order')->values()->map(fn (ProgrammeCyclePaymentPlanItem $item): array => [
                    'name' => $item->name, 'amount' => Money::fromMinor($item->amount_minor),
                ]),
                'installments' => $plan->installments->sortBy('sequence')->values()->map(fn (ProgrammeCyclePaymentPlanInstallment $installment): array => [
                    'name' => $installment->name, 'amount' => Money::fromMinor($installment->amount_minor),
                    'due_on' => $installment->due_on?->toDateString(),
                    'due_offset_days' => $installment->due_offset_days,
                ]),
            ])->values(),
        ]);
    }

    public function store(
        SaveProgrammeCyclePaymentPlanRequest $request,
        AcademyCycle $cycle,
        ProgrammeCycle $programmeCycle,
        AcademyContext $context,
        SaveProgrammeCyclePaymentPlan $save,
    ): RedirectResponse {
        $save->handle($context->academy(), $programmeCycle, $request->user(), $request->validated(), $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Payment plan created.']);

        return back();
    }

    public function update(
        SaveProgrammeCyclePaymentPlanRequest $request,
        AcademyCycle $cycle,
        ProgrammeCycle $programmeCycle,
        ProgrammeCyclePaymentPlan $paymentPlan,
        AcademyContext $context,
        SaveProgrammeCyclePaymentPlan $save,
    ): RedirectResponse {
        $save->handle($context->academy(), $programmeCycle, $request->user(), $request->validated(), $request, $paymentPlan);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Payment plan updated.']);

        return back();
    }
}
