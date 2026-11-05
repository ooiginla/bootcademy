<?php

namespace Tests\Feature\Academies;

use App\Enums\AcademyRole;
use App\Models\Academy;
use App\Models\AcademyMembership;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BranchManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_a_tenant_scoped_branch(): void
    {
        [$owner, $academy] = $this->member(AcademyRole::OwnerAdmin);

        $this->actingAs($owner)
            ->withSession(['active_academy_id' => $academy->id])
            ->post(route('academy.branches.store'), $this->payload())
            ->assertRedirect()
            ->assertInertiaFlash('toast.message', 'Branch created.');

        $this->assertDatabaseHas(Branch::class, [
            'academy_id' => $academy->id,
            'name' => 'Central Campus',
            'code' => 'CENTRAL',
            'country_code' => 'NG',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas(AuditLog::class, [
            'academy_id' => $academy->id,
            'actor_user_id' => $owner->id,
            'action' => 'academy.branch.created',
        ]);
    }

    public function test_branch_fields_are_validated_and_names_are_unique_within_an_academy(): void
    {
        [$owner, $academy] = $this->member(AcademyRole::OwnerAdmin);
        Branch::factory()->for($academy)->create(['name' => 'Central Campus']);

        $this->actingAs($owner)
            ->withSession(['active_academy_id' => $academy->id])
            ->post(route('academy.branches.store'), [
                ...$this->payload(),
                'name' => 'Central Campus',
                'timezone' => 'Not/A_Timezone',
                'country_code' => 'Nigeria',
            ])
            ->assertSessionHasErrors(['name', 'timezone', 'country_code']);
    }

    public function test_staff_can_view_branches_but_cannot_manage_them(): void
    {
        [$coach, $academy] = $this->member(AcademyRole::Coach);
        Branch::factory()->for($academy)->create(['name' => 'North Centre']);

        $this->actingAs($coach)
            ->withSession(['active_academy_id' => $academy->id])
            ->get(route('academy.branches.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('academies/branches')
                ->where('can_manage', false)
                ->where('branches.0.name', 'North Centre')
            );

        $this->actingAs($coach)
            ->withSession(['active_academy_id' => $academy->id])
            ->post(route('academy.branches.store'), $this->payload())
            ->assertForbidden();
    }

    public function test_cross_tenant_branch_update_returns_not_found(): void
    {
        [$owner, $academy] = $this->member(AcademyRole::OwnerAdmin);
        $otherBranch = Branch::factory()->create(['name' => 'Other Academy Branch']);

        $this->actingAs($owner)
            ->withSession(['active_academy_id' => $academy->id])
            ->patch(route('academy.branches.update', $otherBranch), $this->payload())
            ->assertNotFound();

        $this->assertSame('Other Academy Branch', $otherBranch->fresh()->name);
    }

    public function test_owner_can_update_and_deactivate_without_deleting_history(): void
    {
        [$owner, $academy] = $this->member(AcademyRole::OwnerAdmin);
        $branch = Branch::factory()->for($academy)->create();

        $this->actingAs($owner)
            ->withSession(['active_academy_id' => $academy->id])
            ->patch(route('academy.branches.update', $branch), [
                ...$this->payload(),
                'name' => 'Renamed Centre',
                'is_active' => false,
            ])
            ->assertRedirect()
            ->assertInertiaFlash('toast.message', 'Branch updated.');

        $this->assertDatabaseHas(Branch::class, [
            'id' => $branch->id,
            'academy_id' => $academy->id,
            'name' => 'Renamed Centre',
            'is_active' => false,
        ]);
    }

    public function test_super_admin_acting_as_owner_can_manage_branches_without_membership(): void
    {
        $superAdmin = User::factory()->create(['is_platform_super_admin' => true]);
        $academy = Academy::factory()->create();

        $this->actingAs($superAdmin)
            ->post(route('platform.academies.support-access.start', $academy))
            ->assertRedirect(route('dashboard'));

        $this->actingAs($superAdmin)
            ->post(route('academy.branches.store'), $this->payload())
            ->assertRedirect()
            ->assertInertiaFlash('toast.message', 'Branch created.');

        $this->assertDatabaseHas(Branch::class, [
            'academy_id' => $academy->id,
            'name' => 'Central Campus',
        ]);
        $this->assertDatabaseMissing(AcademyMembership::class, [
            'academy_id' => $academy->id,
            'user_id' => $superAdmin->id,
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'name' => 'Central Campus',
            'code' => 'central',
            'address_line' => '12 Academy Road',
            'city' => 'Lagos',
            'state_region' => 'Lagos',
            'country_code' => 'ng',
            'timezone' => 'Africa/Lagos',
            'contact_email' => 'central@example.test',
            'contact_phone' => '+2348000000000',
            'is_active' => true,
        ];
    }

    /** @return array{User, Academy} */
    private function member(AcademyRole $role): array
    {
        $user = User::factory()->create();
        $academy = Academy::factory()->for($user, 'creator')->create();
        AcademyMembership::factory()->for($user)->for($academy)->asRole($role)->create();

        return [$user, $academy];
    }
}
