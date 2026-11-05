<?php

namespace Database\Factories;

use App\Models\Academy;
use App\Models\Guardian;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Guardian> */
class GuardianFactory extends Factory
{
    public function definition(): array
    {
        return [
            'academy_id' => Academy::factory(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('+23480########'),
        ];
    }
}
