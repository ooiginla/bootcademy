<?php

namespace App\Platform\Subscriptions;

use App\Enums\SubscriptionStatus;
use App\Models\Academy;
use App\Models\AcademySubscription;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\SubscriptionEvent;
use Illuminate\Support\Facades\DB;

/** Starts a new academy's free trial on the platform's trial plan (Enterprise by default). */
class StartAcademyTrial
{
    public function handle(Academy $academy): ?AcademySubscription
    {
        $settings = PlatformSetting::current();
        $plan = $settings->trial_plan_id ? Plan::query()->find($settings->trial_plan_id) : null;
        if ($plan === null) {
            return null;
        }

        return DB::transaction(function () use ($academy, $plan, $settings): AcademySubscription {
            $subscription = AcademySubscription::query()->firstOrCreate(
                ['academy_id' => $academy->id],
                ['plan_id' => $plan->id, 'status' => SubscriptionStatus::Active, 'starts_at' => now()],
            );
            if ($subscription->periods()->exists()) {
                return $subscription;
            }
            $period = $subscription->periods()->create([
                'academy_id' => $academy->id,
                'plan_id' => $plan->id,
                'kind' => 'trial',
                'starts_at' => now(),
                'ends_at' => now()->addDays(max(1, $settings->trial_days)),
                'note' => 'Free trial',
            ]);
            SubscriptionEvent::query()->create([
                'academy_subscription_id' => $subscription->id,
                'academy_id' => $academy->id,
                'event' => 'subscription.trial.started',
                'before' => null,
                'after' => ['period_id' => $period->id, 'plan_id' => $plan->id, 'ends_at' => $period->ends_at->toIso8601String()],
            ]);

            return $subscription;
        });
    }
}
