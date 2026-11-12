<?php

namespace App\Http\Controllers\Academies;

use App\Academies\AcademyContext;
use App\Academies\Actions\SaveAcademyCycle;
use App\Academies\Actions\SaveAcademyCycleFee;
use App\Academies\Actions\SaveAcademyCycleTerm;
use App\Academies\Actions\SaveProgrammeCycle;
use App\Academies\Actions\SoftDeleteAcademyRecord;
use App\Http\Controllers\Controller;
use App\Http\Requests\Academies\SaveAcademyCycleFeeRequest;
use App\Http\Requests\Academies\SaveAcademyCycleRequest;
use App\Http\Requests\Academies\SaveAcademyCycleTermRequest;
use App\Http\Requests\Academies\SaveProgrammeCycleRequest;
use App\Models\AcademyCycle;
use App\Models\AcademyCycleFee;
use App\Models\AcademyCycleTerm;
use App\Models\ProgrammeCycle;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AcademyCycleController extends Controller
{
    public function index(AcademyContext $context): Response
    {
        abort_unless($context->canManageFinance(), 403);

        return Inertia::render('academies/cycles', [
            'cycles' => $context->academy()->cycles()->withCount(['terms', 'programmeCycles', 'enrollments'])
                ->orderByRaw("CASE status WHEN 'active' THEN 1 WHEN 'draft' THEN 2 ELSE 3 END")
                ->orderByDesc('starts_on')->get()
                ->map(fn (AcademyCycle $cycle): array => $this->cycleData($cycle)),
        ]);
    }

    public function store(SaveAcademyCycleRequest $request, AcademyContext $context, SaveAcademyCycle $save): RedirectResponse
    {
        $cycle = $save->handle($context->academy(), $request->user(), $request->validated(), $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cycle created.']);

        return to_route('academy.cycles.show', $cycle);
    }

    public function update(
        SaveAcademyCycleRequest $request,
        AcademyCycle $cycle,
        AcademyContext $context,
        SaveAcademyCycle $save,
    ): RedirectResponse {
        $save->handle($context->academy(), $request->user(), $request->validated(), $request, $cycle);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cycle updated.']);

        return back();
    }

    public function storeTerm(SaveAcademyCycleTermRequest $request, AcademyCycle $cycle, AcademyContext $context, SaveAcademyCycleTerm $save): RedirectResponse
    {
        $save->handle($context->academy(), $cycle, $request->user(), $request->validated(), $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Term created.']);

        return back();
    }

    public function updateTerm(SaveAcademyCycleTermRequest $request, AcademyCycle $cycle, AcademyCycleTerm $term, AcademyContext $context, SaveAcademyCycleTerm $save): RedirectResponse
    {
        $save->handle($context->academy(), $cycle, $request->user(), $request->validated(), $request, $term);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Term updated.']);

        return back();
    }

    public function show(AcademyCycle $cycle, AcademyContext $context): Response
    {
        abort_unless($context->canManageFinance(), 403);
        abort_unless($cycle->academy_id === $context->id(), 404);
        $cycle->load([
            'terms' => fn ($query) => $query->orderBy('sort_order')->orderBy('starts_on'),
            'programmeCycles.programme', 'programmeCycles.branch',
            'programmeCycles.feeItems.term', 'programmeCycles.feeItems.cohort',
            'programmeCycles.installments', 'programmeCycles.cohorts.branch',
            'programmeCycles.paymentPlans' => fn ($query) => $query->orderBy('sort_order')->orderBy('name'),
        ])
            ->loadCount(['terms', 'programmeCycles', 'enrollments']);

        return Inertia::render('academies/cycle-details', [
            'cycle' => [
                ...$this->cycleData($cycle),
                'offerings' => $cycle->programmeCycles->map(fn (ProgrammeCycle $offering): array => [
                    'id' => $offering->id, 'programme_id' => $offering->programme_id,
                    'fee_name' => $offering->fee_name,
                    'branch_id' => $offering->branch_id, 'branch_name' => $offering->branch?->name,
                    'programme_name' => $offering->programme->name, 'currency' => $offering->currency,
                    'application_fee' => Money::fromMinor($offering->application_fee_minor),
                    'fee_total' => Money::fromMinor((int) $offering->feeItems->sum('amount_minor')),
                    'payment_mode' => $offering->payment_mode->value,
                    'enrollment_payment_rule' => $offering->enrollment_payment_rule->value,
                    'is_active' => $offering->is_active,
                    'fee_items' => $offering->feeItems->sortBy('sort_order')->values()->map(fn ($item): array => [
                        'id' => $item->id, 'name' => $item->name, 'description' => $item->description,
                        'amount' => Money::fromMinor((int) $item->amount_minor),
                        'academy_cycle_term_id' => $cycle->supportsTerms() ? $item->academy_cycle_term_id : null,
                        'term_name' => $cycle->supportsTerms() ? $item->term?->name : null,
                        'cohort_id' => $item->cohort_id,
                        'cohort_name' => $item->cohort?->name,
                    ]),
                    'installments' => $offering->installments->sortBy('sequence')->values()->map(fn ($item): array => [
                        'name' => $item->name, 'amount' => Money::fromMinor($item->amount_minor),
                        'due_on' => $item->due_on?->toDateString(), 'due_offset_days' => $item->due_offset_days,
                    ]),
                    'scopes' => $this->pricingScopes($cycle, $offering),
                ]),
                'terms' => ($cycle->supportsTerms() ? $cycle->terms : collect())->map(fn (AcademyCycleTerm $term): array => [
                    'id' => $term->id, 'name' => $term->name,
                    'starts_on' => $term->starts_on?->toDateString(), 'ends_on' => $term->ends_on?->toDateString(),
                    'sort_order' => $term->sort_order, 'is_default' => $term->is_default, 'is_active' => $term->is_active,
                ])->values(),
            ],
            'currency' => $context->academy()->currency,
            'programmes' => $context->academy()->programmes()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'branches' => $context->academy()->branches()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'cohorts' => $cycle->programmeCycles->flatMap(fn (ProgrammeCycle $offering) => $offering->cohorts->map(fn ($cohort): array => [
                'id' => $cohort->id, 'name' => $cohort->name, 'programme_cycle_id' => $offering->id,
                'programme_id' => $offering->programme_id, 'programme_name' => $offering->programme->name,
                'branch_id' => $cohort->branch_id, 'branch_name' => $cohort->branch?->name,
            ]))->values(),
            'cycle_fees' => $cycle->fees()->with(['programmes:id,name', 'term:id,name', 'cohort:id,name'])->orderBy('name')->get()->map(fn ($fee): array => [
                'id' => $fee->id, 'name' => $fee->name, 'category' => $fee->category,
                'amount' => Money::fromMinor($fee->amount_minor), 'currency' => $fee->currency,
                'is_compulsory' => $fee->is_compulsory, 'applies_to_all_programmes' => $fee->applies_to_all_programmes,
                'academy_cycle_term_id' => $cycle->supportsTerms() ? $fee->academy_cycle_term_id : null,
                'term_name' => $cycle->supportsTerms() ? $fee->term?->name : null,
                'cohort_id' => $fee->cohort_id, 'cohort_name' => $fee->cohort?->name,
                'is_active' => $fee->is_active, 'programmes' => $fee->programmes->map->only(['id', 'name'])->values(),
            ]),
        ]);
    }

    public function storeOffering(
        SaveProgrammeCycleRequest $request,
        AcademyCycle $cycle,
        AcademyContext $context,
        SaveProgrammeCycle $save,
    ): RedirectResponse {
        $offering = $save->handle($context->academy(), $cycle, $request->user(), $request->validated(), $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Programme fee added. Now add its payment plans.']);

        return to_route('academy.cycles.offerings.show', ['cycle' => $cycle, 'programmeCycle' => $offering]);
    }

    public function storeFee(SaveAcademyCycleFeeRequest $request, AcademyCycle $cycle, AcademyContext $context, SaveAcademyCycleFee $save): RedirectResponse
    {
        $save->handle($context->academy(), $cycle, $request->user(), $request->validated(), $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cycle fee created.']);

        return back();
    }

    public function updateOffering(
        SaveProgrammeCycleRequest $request,
        AcademyCycle $cycle,
        ProgrammeCycle $programmeCycle,
        AcademyContext $context,
        SaveProgrammeCycle $save,
    ): RedirectResponse {
        $save->handle($context->academy(), $cycle, $request->user(), $request->validated(), $request, $programmeCycle);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Programme cycle fees updated.']);

        return back();
    }

    /**
     * One row per place a programme fee is priced: the whole cycle (always listed) plus any term or
     * cohort that has its own payment plans.
     *
     * @return list<array<string, mixed>>
     */
    private function pricingScopes(AcademyCycle $cycle, ProgrammeCycle $offering): array
    {
        $plans = $offering->paymentPlans
            ->when(! $cycle->supportsTerms(), fn ($plans) => $plans->where('term_scope_id', 0))
            ->groupBy(fn ($plan): string => $plan->term_scope_id.':'.$plan->cohort_scope_id);
        $plans->put('0:0', $plans->get('0:0', collect()));
        $terms = $cycle->terms->keyBy('id');
        $cohorts = $offering->cohorts->keyBy('id');

        return array_values($plans->map(function ($scopePlans, string $key) use ($terms, $cohorts): array {
            [$termId, $cohortId] = array_map('intval', explode(':', $key));
            $default = $scopePlans->firstWhere('is_default', true) ?? $scopePlans->firstWhere('is_active', true);

            return [
                'academy_cycle_term_id' => $termId ?: null,
                'term_name' => $termId ? $terms->get($termId)?->name : null,
                'cohort_id' => $cohortId ?: null,
                'cohort_name' => $cohortId ? $cohorts->get($cohortId)?->name : null,
                'plans_count' => $scopePlans->count(),
                'public_plans_count' => $scopePlans->where('is_active', true)->where('is_public', true)->count(),
                'default_plan_name' => $default?->name,
                'default_total' => $default === null ? null : Money::fromMinor((int) $default->total_minor),
            ];
        })->sortBy(fn (array $scope): string => sprintf('%010d:%010d', $scope['academy_cycle_term_id'] ?? 0, $scope['cohort_id'] ?? 0))
            ->all());
    }

    /** @return array<string, mixed> */
    private function cycleData(AcademyCycle $cycle): array
    {
        return [
            'id' => $cycle->id, 'name' => $cycle->name, 'type' => $cycle->type->value,
            'type_label' => $cycle->type->label(), 'starts_on' => $cycle->starts_on?->toDateString(),
            'ends_on' => $cycle->ends_on?->toDateString(), 'duration_value' => $cycle->duration_value,
            'duration_unit' => $cycle->duration_unit?->value,
            'status' => $cycle->status->value, 'programme_cycles_count' => $cycle->programme_cycles_count ?? 0,
            'enrollments_count' => $cycle->enrollments_count ?? 0,
            'terms_count' => $cycle->supportsTerms() ? ($cycle->terms_count ?? 0) : 0,
        ];
    }

    public function destroy(Request $request, AcademyCycle $cycle, AcademyContext $context, SoftDeleteAcademyRecord $delete): RedirectResponse
    {
        abort_unless($context->canManageFinance(), 403);
        $delete->handle($context->academy(), $request->user(), $cycle, 'cycle', $cycle->name, $cycle->only(['type', 'status']), $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cycle deleted.']);

        return to_route('academy.cycles.index');
    }

    public function destroyFee(Request $request, AcademyCycle $cycle, AcademyCycleFee $fee, AcademyContext $context, SoftDeleteAcademyRecord $delete): RedirectResponse
    {
        abort_unless($context->canManageFinance(), 403);
        abort_unless($cycle->academy_id === $context->id() && $fee->academy_cycle_id === $cycle->id, 404);
        $delete->handle($context->academy(), $request->user(), $fee, 'cycle fee', $fee->name, ['academy_cycle_id' => $cycle->id, 'amount_minor' => $fee->amount_minor, 'currency' => $fee->currency], $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cycle fee deleted.']);

        return back();
    }

    public function destroyOffering(Request $request, AcademyCycle $cycle, ProgrammeCycle $programmeCycle, AcademyContext $context, SoftDeleteAcademyRecord $delete): RedirectResponse
    {
        abort_unless($context->canManageFinance(), 403);
        abort_unless($cycle->academy_id === $context->id() && $programmeCycle->academy_cycle_id === $cycle->id, 404);
        $programmeCycle->loadMissing('programme');
        $name = $programmeCycle->fee_name ?: ($programmeCycle->programme->name ?? 'Programme fee');
        $delete->handle($context->academy(), $request->user(), $programmeCycle, 'programme fee', $name, [
            'academy_cycle_id' => $cycle->id,
            'programme_id' => $programmeCycle->programme_id,
            'branch_id' => $programmeCycle->branch_id,
        ], $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Programme fee deleted.']);

        return to_route('academy.cycles.show', $cycle);
    }
}
