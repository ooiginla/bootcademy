<?php

namespace App\Platform\Actions;

use App\Enums\SubscriptionStatus;
use App\Models\Academy;
use App\Models\AcademySubscription;
use App\Models\Plan;
use App\Models\SubscriptionEvent;
use App\Models\User;
use App\Platform\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AssignAcademySubscription
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(
        Academy $academy,
        Plan $plan,
        SubscriptionStatus $status,
        User $actor,
        Request $request,
    ): AcademySubscription {
        return DB::transaction(function () use ($academy, $plan, $status, $actor, $request): AcademySubscription {
            $subscription = AcademySubscription::query()
                ->where('academy_id', $academy->id)
                ->lockForUpdate()
                ->first();

            $before = $subscription ? [
                'plan_id' => $subscription->plan_id,
                'status' => $subscription->status->value,
            ] : null;

            if ($subscription) {
                $subscription->update([
                    'plan_id' => $plan->id,
                    'status' => $status,
                    'assigned_by_user_id' => $actor->id,
                ]);
            } else {
                $subscription = AcademySubscription::create([
                    'academy_id' => $academy->id,
                    'plan_id' => $plan->id,
                    'status' => $status,
                    'starts_at' => now(),
                    'assigned_by_user_id' => $actor->id,
                ]);
            }

            $after = ['plan_id' => $plan->id, 'status' => $status->value];

            SubscriptionEvent::create([
                'academy_subscription_id' => $subscription->id,
                'academy_id' => $academy->id,
                'actor_user_id' => $actor->id,
                'event' => $before ? 'subscription.changed' : 'subscription.assigned',
                'before' => $before,
                'after' => $after,
            ]);

            $this->audit->record(
                $before ? 'academy.subscription.changed' : 'academy.subscription.assigned',
                $actor,
                $academy,
                $subscription,
                ['before' => $before, 'after' => $after],
                $request,
            );

            return $subscription;
        });
    }
}
