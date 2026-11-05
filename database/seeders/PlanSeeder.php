<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'code' => 'essential',
                'name' => 'Essential',
                'monthly_price_minor' => 2_500_000,
                'quarterly_price_minor' => 7_500_000,
                'annual_price_minor' => 27_000_000,
                'active_player_limit' => 100,
                'sort_order' => 10,
            ],
            [
                'code' => 'professional',
                'name' => 'Professional',
                'monthly_price_minor' => 4_500_000,
                'quarterly_price_minor' => 13_500_000,
                'annual_price_minor' => 48_600_000,
                'active_player_limit' => 300,
                'sort_order' => 20,
            ],
            [
                'code' => 'enterprise',
                'name' => 'Enterprise',
                'monthly_price_minor' => 8_500_000,
                'quarterly_price_minor' => 25_500_000,
                'annual_price_minor' => 91_800_000,
                'active_player_limit' => 1_000,
                'sort_order' => 30,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::query()->updateOrCreate(
                ['code' => $plan['code']],
                [...$plan, 'currency' => 'NGN', 'is_active' => true],
            );
        }
    }
}
