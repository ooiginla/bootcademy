<?php

namespace App\Http\Controllers\Academies;

use App\Academies\AcademyContext;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionController extends Controller
{
    public function show(AcademyContext $context): Response
    {
        Gate::authorize('manage', $context->academy());
        $subscription = $context->academy()->subscription()->with('plan')->first();

        return Inertia::render('academies/subscription', [
            'subscription' => $subscription ? [
                'status' => $subscription->status->value,
                'status_label' => $subscription->status->label(),
                'starts_at' => $subscription->starts_at->toIso8601String(),
                'plan' => [
                    'name' => $subscription->plan->name,
                    'currency' => $subscription->plan->currency,
                    'monthly_minor' => $subscription->plan->monthly_price_minor,
                    'quarterly_minor' => $subscription->plan->quarterly_price_minor,
                    'annual_minor' => $subscription->plan->annual_price_minor,
                    'active_player_limit' => $subscription->plan->active_player_limit,
                ],
            ] : null,
        ]);
    }
}
