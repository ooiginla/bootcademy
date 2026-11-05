<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\UpdatePlanRequest;
use App\Models\Plan;
use App\Platform\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PlanController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('platform/plans/index', [
            'plans' => Plan::query()
                ->withCount('subscriptions')
                ->orderBy('sort_order')
                ->get()
                ->map(fn (Plan $plan): array => $this->present($plan)),
        ]);
    }

    public function edit(Plan $plan): Response
    {
        return Inertia::render('platform/plans/edit', [
            'plan' => $this->present($plan),
        ]);
    }

    public function update(
        UpdatePlanRequest $request,
        Plan $plan,
        AuditLogger $audit,
    ): RedirectResponse {
        $before = $plan->only([
            'name',
            'monthly_price_minor',
            'quarterly_price_minor',
            'annual_price_minor',
            'active_player_limit',
            'is_active',
        ]);

        $plan->update([
            'name' => $request->string('name')->toString(),
            'monthly_price_minor' => $request->integer('monthly_ngn') * 100,
            'quarterly_price_minor' => $request->integer('quarterly_ngn') * 100,
            'annual_price_minor' => $request->integer('annual_ngn') * 100,
            'active_player_limit' => $request->integer('active_player_limit'),
            'is_active' => $request->boolean('is_active'),
        ]);

        $audit->record('platform.plan.updated', $request->user(), null, $plan, [
            'before' => $before,
            'after' => $plan->only(array_keys($before)),
        ], $request);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Plan updated.']);

        return to_route('platform.plans.index');
    }

    /** @return array<string, mixed> */
    private function present(Plan $plan): array
    {
        return [
            'id' => $plan->id,
            'code' => $plan->code,
            'name' => $plan->name,
            'currency' => $plan->currency,
            'monthly_ngn' => intdiv($plan->monthly_price_minor, 100),
            'quarterly_ngn' => intdiv($plan->quarterly_price_minor, 100),
            'annual_ngn' => intdiv($plan->annual_price_minor, 100),
            'active_player_limit' => $plan->active_player_limit,
            'is_active' => $plan->is_active,
            'subscriptions_count' => $plan->subscriptions_count ?? 0,
        ];
    }
}
