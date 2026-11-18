<?php

namespace App\Http\Controllers\Platform;

use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\Academy;
use App\Models\AcademyPlanPrice;
use App\Models\AcademySubscription;
use App\Models\AcademySubscriptionPeriod;
use App\Models\Plan;
use App\Models\SubscriptionEvent;
use App\Models\SubscriptionPayment;
use App\Platform\AcademyEntitlements;
use App\Platform\AuditLogger;
use App\Platform\Subscriptions\AddSubscriptionPeriod;
use App\Platform\Subscriptions\SubscriptionPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Platform view of one academy's subscription: status, periods, payments and its own prices. */
class AcademySubscriptionController extends Controller
{
    public function edit(Academy $academy, SubscriptionPresenter $presenter): Response
    {
        $entitlements = AcademyEntitlements::for($academy);
        $subscription = $entitlements->subscription();
        $prices = $academy->planPrices()->get()->keyBy('plan_id');

        return Inertia::render('platform/academies/subscription', [
            'managedAcademy' => ['id' => $academy->id, 'name' => $academy->name],
            'state' => $entitlements->state(),
            'status' => $subscription?->status->value ?? SubscriptionStatus::Active->value,
            'plan_name' => $entitlements->plan()?->name,
            'access_ends_at' => $entitlements->accessEndsAt()?->toIso8601String(),
            'periods' => $subscription === null ? [] : $subscription->periods()->with('plan')->latest('starts_at')->limit(20)->get()
                ->map(fn (AcademySubscriptionPeriod $period) => $presenter->period($period))->values(),
            'payments' => $academy->subscriptionPayments()->with(['plan', 'period'])->latest('id')->limit(20)->get()
                ->map(fn (SubscriptionPayment $payment): array => $presenter->payment($payment))->values(),
            'plans' => Plan::query()->orderBy('sort_order')->get()->map(fn (Plan $plan): array => [
                'id' => $plan->id,
                'name' => $plan->name,
                'is_active' => $plan->is_active,
                'list' => [
                    'monthly' => $plan->monthly_price_minor,
                    'quarterly' => $plan->quarterly_price_minor,
                    'annual' => $plan->annual_price_minor,
                ],
                'custom' => ($price = $prices->get($plan->id)) instanceof AcademyPlanPrice ? [
                    'monthly' => $price->monthly_price_minor,
                    'quarterly' => $price->quarterly_price_minor,
                    'annual' => $price->annual_price_minor,
                ] : null,
            ])->values(),
            'statuses' => collect(SubscriptionStatus::cases())->map(fn (SubscriptionStatus $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
            ]),
        ]);
    }

    /** Suspends or reactivates the academy. Suspension blocks the staff app even during a paid period. */
    public function update(Request $request, Academy $academy, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate(['status' => ['required', Rule::enum(SubscriptionStatus::class)]]);
        $status = SubscriptionStatus::from((string) $validated['status']);

        DB::transaction(function () use ($academy, $status, $request, $audit): void {
            $subscription = AcademySubscription::query()->where('academy_id', $academy->id)->lockForUpdate()->first();
            if ($subscription === null) {
                $plan = Plan::query()->orderBy('sort_order')->firstOrFail();
                $subscription = AcademySubscription::query()->create([
                    'academy_id' => $academy->id, 'plan_id' => $plan->id, 'status' => $status,
                    'starts_at' => now(), 'assigned_by_user_id' => $request->user()->id,
                ]);
                $before = null;
            } else {
                $before = ['status' => $subscription->status->value];
                $subscription->update(['status' => $status, 'assigned_by_user_id' => $request->user()->id]);
            }
            SubscriptionEvent::query()->create([
                'academy_subscription_id' => $subscription->id,
                'academy_id' => $academy->id,
                'actor_user_id' => $request->user()->id,
                'event' => 'subscription.status.changed',
                'before' => $before,
                'after' => ['status' => $status->value],
            ]);
            $audit->record('academy.subscription.status-changed', $request->user(), $academy, $subscription, [
                'before' => $before, 'after' => ['status' => $status->value],
            ], $request);
        });
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Academy subscription updated.']);

        return back();
    }

    /** Gives an academy access without an online payment, for example after a bank transfer. */
    public function grant(Request $request, Academy $academy, AddSubscriptionPeriod $addPeriod): RedirectResponse
    {
        $validated = $request->validate([
            'plan_id' => ['required', 'integer', Rule::exists('plans', 'id')],
            'ends_on' => ['required', 'date', 'after:today'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $plan = Plan::query()->findOrFail((int) $validated['plan_id']);
        $timezone = $academy->timezone ?: 'UTC';
        $endsAt = Carbon::parse((string) $validated['ends_on'], $timezone)->endOfDay()->utc();
        $startsAt = AcademyEntitlements::for($academy)->paidThrough();
        if ($startsAt !== null && $startsAt->gte($endsAt)) {
            return back()->withErrors(['ends_on' => 'This academy is already paid up past that date. Choose a later end date.']);
        }

        $addPeriod->handle($academy, $plan, 'granted', null, null, $request->user(), $request, $endsAt, $validated['note'] ?? null);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Access granted.']);

        return back();
    }

    /** Sets the prices this academy pays for a plan. */
    public function savePrice(Request $request, Academy $academy, Plan $plan, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'monthly_ngn' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'quarterly_ngn' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'annual_ngn' => ['required', 'integer', 'min:1', 'max:1000000000'],
        ]);
        $price = AcademyPlanPrice::query()->updateOrCreate(
            ['academy_id' => $academy->id, 'plan_id' => $plan->id],
            [
                'monthly_price_minor' => (int) $validated['monthly_ngn'] * 100,
                'quarterly_price_minor' => (int) $validated['quarterly_ngn'] * 100,
                'annual_price_minor' => (int) $validated['annual_ngn'] * 100,
                'set_by_user_id' => $request->user()->id,
            ],
        );
        $audit->record('academy.subscription.price-set', $request->user(), $academy, $price, [
            'plan_id' => $plan->id,
            'prices_minor' => $price->only(['monthly_price_minor', 'quarterly_price_minor', 'annual_price_minor']),
        ], $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$plan->name} price saved for {$academy->name}."]);

        return back();
    }

    public function destroyPrice(Request $request, Academy $academy, Plan $plan, AuditLogger $audit): RedirectResponse
    {
        $price = AcademyPlanPrice::query()->where('academy_id', $academy->id)->where('plan_id', $plan->id)->first();
        if ($price !== null) {
            $audit->record('academy.subscription.price-removed', $request->user(), $academy, $price, ['plan_id' => $plan->id], $request);
            $price->delete();
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$academy->name} now pays the {$plan->name} list price."]);

        return back();
    }
}
