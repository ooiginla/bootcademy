<?php

namespace Database\Factories;

use App\Enums\AcademyInvitationStatus;
use App\Enums\AcademyRole;
use App\Models\Academy;
use App\Models\AcademyInvitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<AcademyInvitation> */
class AcademyInvitationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'academy_id' => Academy::factory(),
            'email' => fake()->unique()->safeEmail(),
            'role' => AcademyRole::Coach,
            'status' => AcademyInvitationStatus::Pending,
            'token_hash' => hash('sha256', Str::random(64)),
            'invited_by_user_id' => User::factory(),
            'expires_at' => now()->addDays(7),
            'accepted_by_user_id' => null,
            'accepted_at' => null,
            'revoked_at' => null,
        ];
    }
}
