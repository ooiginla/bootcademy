<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Plan> */
class PlanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->slug(2),
            'name' => fake()->unique()->word().' Plan',
            'currency' => 'NGN',
            'monthly_price_minor' => 2_500_000,
            'quarterly_price_minor' => 7_500_000,
            'annual_price_minor' => 27_000_000,
            'active_player_limit' => 100,
            'is_active' => true,
            'sort_order' => 10,
        ];
    }
}
