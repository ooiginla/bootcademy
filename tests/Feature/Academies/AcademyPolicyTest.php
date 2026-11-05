<?php

namespace Tests\Feature\Academies;

use App\Enums\AcademyRole;
use App\Enums\MembershipStatus;
use App\Models\Academy;
use App\Models\AcademyMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AcademyPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_active_owner_admin_has_academy_management_permission(): void
    {
        $academy = Academy::factory()->create();
        $owner = User::factory()->create();
        AcademyMembership::factory()->for($owner)->for($academy)->create();

        $this->assertTrue(Gate::forUser($owner)->allows('manage', $academy));

        foreach ([
            AcademyRole::FinanceOfficer,
            AcademyRole::BranchManager,
            AcademyRole::Coach,
            AcademyRole::Guardian,
            AcademyRole::ReadOnlyAuditor,
        ] as $role) {
            $member = User::factory()->create();
            AcademyMembership::factory()->for($member)->for($academy)->asRole($role)->create();
            $this->assertFalse(Gate::forUser($member)->allows('manage', $academy));
        }

        $suspendedOwner = User::factory()->create();
        AcademyMembership::factory()->for($suspendedOwner)->for($academy)->create([
            'status' => MembershipStatus::Suspended,
        ]);
        $this->assertFalse(Gate::forUser($suspendedOwner)->allows('manage', $academy));
    }

    public function test_owner_of_one_academy_cannot_manage_another_academy(): void
    {
        $user = User::factory()->create();
        $ownAcademy = Academy::factory()->for($user, 'creator')->create();
        AcademyMembership::factory()->for($user)->for($ownAcademy)->create();

        $this->assertFalse(Gate::forUser($user)->allows('manage', Academy::factory()->create()));
    }
}
