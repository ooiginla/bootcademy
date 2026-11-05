<?php

namespace App\Http\Controllers\Platform;

use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\AssignAcademySubscriptionRequest;
use App\Models\Academy;
use App\Models\Plan;
use App\Platform\Actions\AssignAcademySubscription;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AcademySubscriptionController extends Controller
{
    public function edit(Academy $academy): Response
    {
        $subscription = $academy->subscription()->with('plan')->first();

        return Inertia::render('platform/academies/subscription', [
            'managedAcademy' => ['id' => $academy->id, 'name' => $academy->name],
            'subscription' => $subscription ? [
                'plan_id' => $subscription->plan_id,
                'plan_name' => $subscription->plan->name,
                'status' => $subscription->status->value,
                'starts_at' => $subscription->starts_at->toIso8601String(),
            ] : null,
            'plans' => Plan::query()
                ->where('is_active', true)
                ->when($subscription, fn ($query) => $query->orWhereKey($subscription?->plan_id))
                ->orderBy('sort_order')
                ->get()
                ->map(fn (Plan $plan): array => [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'active_player_limit' => $plan->active_player_limit,
                    'monthly_ngn' => intdiv($plan->monthly_price_minor, 100),
                ]),
            'statuses' => collect(SubscriptionStatus::cases())->map(fn (SubscriptionStatus $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
            ]),
        ]);
    }

    public function update(
        AssignAcademySubscriptionRequest $request,
        Academy $academy,
        AssignAcademySubscription $assign,
    ): RedirectResponse {
        $plan = Plan::query()->findOrFail($request->integer('plan_id'));
        $status = $request->enum('status', SubscriptionStatus::class);
        abort_unless($status instanceof SubscriptionStatus, 422);

        $assign->handle($academy, $plan, $status, $request->user(), $request);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Academy subscription updated.']);

        return back();
    }
}
