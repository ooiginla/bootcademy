<?php

namespace Database\Factories;

use App\Models\Academy;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Academy> */
class AcademyFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company().' Academy';

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'academy_type' => 'general',
            'timezone' => 'Africa/Lagos',
            'currency' => 'NGN',
            'logo_path' => null,
            'primary_color' => null,
            'secondary_color' => null,
            'created_by_user_id' => User::factory(),
        ];
    }
}
