<?php

namespace Database\Factories;

use App\Enums\SubscriptionStatus;
use App\Models\Academy;
use App\Models\AcademySubscription;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AcademySubscription> */
class AcademySubscriptionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'academy_id' => Academy::factory(),
            'plan_id' => Plan::factory(),
            'status' => SubscriptionStatus::Active,
            'starts_at' => now(),
            'assigned_by_user_id' => User::factory(),
        ];
    }
}
