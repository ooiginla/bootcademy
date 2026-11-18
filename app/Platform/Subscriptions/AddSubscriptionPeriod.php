<?php

namespace App\Platform\Subscriptions;

use App\Enums\BillingInterval;
use App\Enums\SubscriptionStatus;
use App\Models\Academy;
use App\Models\AcademySubscription;
use App\Models\AcademySubscriptionPeriod;
use App\Models\Plan;
use App\Models\SubscriptionEvent;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Platform\AuditLogger;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Adds a paid or granted period to an academy's subscription.
 *
 * A new period starts when the academy's paid time runs out, so renewing early never loses time,
 * and a plan change takes effect from the next period. An unfinished free trial ends as soon as
 * the first paid or granted period starts.
 */
class AddSubscriptionPeriod
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(
        Academy $academy,
        Plan $plan,
        string $kind,
        ?BillingInterval $interval,
        ?SubscriptionPayment $payment,
        ?User $actor,
        Request $request,
        ?CarbonInterface $endsAt = null,
        ?string $note = null,
    ): AcademySubscriptionPeriod {
        return DB::transaction(function () use ($academy, $plan, $kind, $interval, $payment, $actor, $request, $endsAt, $note): AcademySubscriptionPeriod {
            $subscription = AcademySubscription::query()->where('academy_id', $academy->id)->lockForUpdate()->first()
                ?? AcademySubscription::query()->create([
                    'academy_id' => $academy->id,
                    'plan_id' => $plan->id,
                    'status' => SubscriptionStatus::Active,
                    'starts_at' => now(),
                    'assigned_by_user_id' => $actor?->id,
                ]);

            $now = now();
            $paidThrough = $subscription->periods()->where('kind', '!=', 'trial')->max('ends_at');
            $startsAt = $paidThrough !== null && Carbon::parse($paidThrough)->gt($now) ? Carbon::parse($paidThrough) : $now->copy();
            $endsAt ??= $startsAt->copy()->addMonthsNoOverflow($interval?->months() ?? 1);

            if ($startsAt->lte($now)) {
                $subscription->periods()->where('kind', 'trial')->where('ends_at', '>', $now)->update(['ends_at' => $now]);
            }

            $period = $subscription->periods()->create([
                'academy_id' => $academy->id,
                'plan_id' => $plan->id,
                'kind' => $kind,
                'billing_interval' => $interval,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'subscription_payment_id' => $payment?->id,
                'granted_by_user_id' => $kind === 'granted' ? $actor?->id : null,
                'note' => $note,
            ]);

            $before = ['plan_id' => $subscription->plan_id];
            if ($startsAt->lte($now)) {
                $subscription->update(['plan_id' => $plan->id]);
            }
            $after = [
                'period_id' => $period->id, 'kind' => $kind, 'plan_id' => $plan->id, 'billing_interval' => $interval?->value,
                'starts_at' => $startsAt->toIso8601String(), 'ends_at' => $endsAt->toIso8601String(),
                'subscription_payment_id' => $payment?->id,
            ];
            SubscriptionEvent::query()->create([
                'academy_subscription_id' => $subscription->id,
                'academy_id' => $academy->id,
                'actor_user_id' => $actor?->id,
                'event' => 'subscription.period.'.$kind,
                'before' => $before,
                'after' => $after,
            ]);
            $this->audit->record('academy.subscription.period-added', $actor, $academy, $period, $after, $request);

            return $period;
        });
    }
}
