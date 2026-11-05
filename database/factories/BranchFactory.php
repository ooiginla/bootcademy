<?php

namespace Database\Factories;

use App\Models\Academy;
use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Branch> */
class BranchFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'academy_id' => Academy::factory(),
            'name' => fake()->unique()->city().' Centre',
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'city' => fake()->city(),
            'state_region' => ucfirst(fake()->word()).' Region',
            'country_code' => fake()->countryCode(),
            'timezone' => 'Africa/Lagos',
            'is_active' => true,
        ];
    }
}
