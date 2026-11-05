<?php

namespace Database\Factories;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use App\Models\Player;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Enrollment> */
class EnrollmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'player_id' => Player::factory(),
            'academy_id' => function (array $attributes): int {
                return Player::query()->whereKey($attributes['player_id'])->firstOrFail()->academy_id;
            },
            'programme_id' => null,
            'branch_id' => null,
            'status' => EnrollmentStatus::Active,
            'starts_on' => today(),
            'ends_on' => null,
        ];
    }
}
