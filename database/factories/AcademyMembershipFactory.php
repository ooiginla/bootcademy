<?php

namespace Database\Factories;

use App\Enums\AcademyRole;
use App\Enums\MembershipStatus;
use App\Models\Academy;
use App\Models\AcademyMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AcademyMembership> */
class AcademyMembershipFactory extends Factory
{
    public function definition(): array
    {
        return [
            'academy_id' => Academy::factory(),
            'user_id' => User::factory(),
            'role' => AcademyRole::OwnerAdmin,
            'status' => MembershipStatus::Active,
            'invited_by_user_id' => null,
            'joined_at' => now(),
        ];
    }

    public function asRole(AcademyRole $role): static
    {
        return $this->state(fn (): array => ['role' => $role]);
    }
}
