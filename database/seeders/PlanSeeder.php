<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Platform\Subscriptions\DefaultPlanCatalog;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $ids = [];
        foreach (DefaultPlanCatalog::plans() as $values) {
            $features = $values['features'];
            unset($values['features']);
            $plan = Plan::query()->updateOrCreate(
                ['code' => $values['code']],
                [...$values, 'currency' => 'NGN', 'is_active' => true],
            );
            foreach ($features as $key => $enabled) {
                $plan->features()->updateOrCreate(['feature_key' => $key], ['enabled' => $enabled]);
            }
            $ids[$plan->code] = $plan->id;
        }

        $settings = PlatformSetting::current();
        $settings->update([
            'trial_plan_id' => $settings->trial_plan_id ?? $ids['enterprise'],
            'post_trial_plan_id' => $settings->post_trial_plan_id ?? $ids['essential'],
        ]);
    }
}
