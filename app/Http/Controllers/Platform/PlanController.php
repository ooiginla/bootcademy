<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\UpdatePlanRequest;
use App\Models\Plan;
use App\Platform\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PlanController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('platform/plans/index', [
            'plans' => Plan::query()
                ->with('features')
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
        $fields = [
            'name', 'description', 'monthly_price_minor', 'quarterly_price_minor', 'annual_price_minor',
            'active_player_limit', 'branch_limit', 'staff_account_limit', 'is_recommended',
            'migration_support', 'support_level', 'is_active',
        ];
        $before = [...$plan->only($fields), 'flags' => $plan->flags()];
        $optionalInt = fn (string $key): ?int => $request->filled($key) ? $request->integer($key) : null;
        $optionalText = fn (string $key): ?string => $request->filled($key) ? trim($request->string($key)->toString()) : null;

        DB::transaction(function () use ($request, $plan, $optionalInt, $optionalText): void {
            $plan->update([
                'name' => $request->string('name')->toString(),
                'description' => $optionalText('description'),
                'monthly_price_minor' => $request->integer('monthly_ngn') * 100,
                'quarterly_price_minor' => $request->integer('quarterly_ngn') * 100,
                'annual_price_minor' => $request->integer('annual_ngn') * 100,
                'active_player_limit' => $request->integer('active_player_limit'),
                'branch_limit' => $optionalInt('branch_limit'),
                'staff_account_limit' => $optionalInt('staff_account_limit'),
                'is_recommended' => $request->boolean('is_recommended'),
                'migration_support' => $optionalText('migration_support'),
                'support_level' => $optionalText('support_level'),
                'is_active' => $request->boolean('is_active'),
            ]);
            foreach (array_keys((array) config('plans.flags')) as $key) {
                $plan->features()->updateOrCreate(
                    ['feature_key' => $key],
                    ['enabled' => (bool) data_get($request->input('flags', []), $key, false)],
                );
            }
        });
        $plan->unsetRelation('features');

        $audit->record('platform.plan.updated', $request->user(), null, $plan, [
            'before' => $before,
            'after' => [...$plan->only($fields), 'flags' => $plan->flags()],
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
            'description' => $plan->description,
            'active_player_limit' => $plan->active_player_limit,
            'branch_limit' => $plan->branch_limit,
            'staff_account_limit' => $plan->staff_account_limit,
            'is_recommended' => $plan->is_recommended,
            'migration_support' => $plan->migration_support,
            'support_level' => $plan->support_level,
            'flags' => $plan->flags(),
            'flag_labels' => (array) config('plans.flags'),
            'is_active' => $plan->is_active,
            'subscriptions_count' => $plan->subscriptions_count ?? 0,
        ];
    }
}
