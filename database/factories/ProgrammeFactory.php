<?php

namespace Database\Factories;

use App\Models\Academy;
use App\Models\Programme;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Programme> */
class ProgrammeFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'academy_id' => Academy::factory(),
            'name' => ucfirst(fake()->unique()->word().' '.fake()->word()),
            'code' => strtoupper(fake()->unique()->lexify('????')),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
