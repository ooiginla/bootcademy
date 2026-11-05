<?php

namespace App\Http\Controllers\Academies;

use App\Academies\AcademyContext;
use App\Academies\Actions\SaveAcademyCycle;
use App\Academies\Actions\SaveAcademyCycleFee;
use App\Academies\Actions\SaveProgrammeCycle;
use App\Http\Controllers\Controller;
use App\Http\Requests\Academies\SaveAcademyCycleFeeRequest;
use App\Http\Requests\Academies\SaveAcademyCycleRequest;
use App\Http\Requests\Academies\SaveProgrammeCycleRequest;
use App\Models\AcademyCycle;
use App\Models\ProgrammeCycle;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AcademyCycleController extends Controller
{
    public function index(AcademyContext $context): Response
    {
        abort_unless($context->canManageFinance(), 403);

        return Inertia::render('academies/cycles', [
            'cycles' => $context->academy()->cycles()->withCount(['programmeCycles', 'enrollments'])
                ->orderByRaw("CASE status WHEN 'active' THEN 1 WHEN 'draft' THEN 2 ELSE 3 END")
                ->orderByDesc('starts_on')->get()
                ->map(fn (AcademyCycle $cycle): array => $this->cycleData($cycle)),
        ]);
    }

    public function store(SaveAcademyCycleRequest $request, AcademyContext $context, SaveAcademyCycle $save): RedirectResponse
    {
        $cycle = $save->handle($context->academy(), $request->user(), $request->validated(), $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Billing cycle created.']);

        return to_route('academy.cycles.show', $cycle);
    }

    public function update(
        SaveAcademyCycleRequest $request,
        AcademyCycle $cycle,
        AcademyContext $context,
        SaveAcademyCycle $save,
    ): RedirectResponse {
        $save->handle($context->academy(), $request->user(), $request->validated(), $request, $cycle);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Billing cycle updated.']);

        return back();
    }

    public function show(AcademyCycle $cycle, AcademyContext $context): Response
    {
        abort_unless($context->canManageFinance(), 403);
        abort_unless($cycle->academy_id === $context->id(), 404);
        $cycle->load(['programmeCycles.programme', 'programmeCycles.branch', 'programmeCycles.feeItems', 'programmeCycles.installments'])
            ->loadCount(['programmeCycles', 'enrollments']);

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
                        'name' => $item->name, 'amount' => Money::fromMinor((int) $item->amount_minor),
                    ]),
                    'installments' => $offering->installments->sortBy('sequence')->values()->map(fn ($item): array => [
                        'name' => $item->name, 'amount' => Money::fromMinor($item->amount_minor),
                        'due_on' => $item->due_on?->toDateString(), 'due_offset_days' => $item->due_offset_days,
                    ]),
                ]),
            ],
            'currency' => $context->academy()->currency,
            'programmes' => $context->academy()->programmes()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'branches' => $context->academy()->branches()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'cycle_fees' => $cycle->fees()->with('programmes:id,name')->orderBy('name')->get()->map(fn ($fee): array => [
                'id' => $fee->id, 'name' => $fee->name, 'category' => $fee->category,
                'amount' => Money::fromMinor($fee->amount_minor), 'currency' => $fee->currency,
                'is_compulsory' => $fee->is_compulsory, 'applies_to_all_programmes' => $fee->applies_to_all_programmes,
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
        $save->handle($context->academy(), $cycle, $request->user(), $request->validated(), $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Programme fees added to cycle.']);

        return back();
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
        ];
    }
}
