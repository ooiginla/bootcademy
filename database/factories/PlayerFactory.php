<?php

namespace Database\Factories;

use App\Enums\PlayerStatus;
use App\Models\Academy;
use App\Models\Player;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Player> */
class PlayerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'academy_id' => Academy::factory(),
            'internal_identifier' => fake()->unique()->bothify('LRN-####'),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'date_of_birth' => fake()->dateTimeBetween('-17 years', '-3 years'),
            'status' => PlayerStatus::Active,
        ];
    }
}
