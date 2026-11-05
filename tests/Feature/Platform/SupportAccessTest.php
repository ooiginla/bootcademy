<?php

namespace Tests\Feature\Platform;

use App\Models\Academy;
use App\Models\AcademyMembership;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SupportAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_super_admin_cannot_enter_support_mode(): void
    {
        $user = User::factory()->create();
        $academy = Academy::factory()->create();

        $this->actingAs($user)
            ->post(route('platform.academies.support-access.start', $academy))
            ->assertForbidden();

        $this->assertDatabaseMissing(AuditLog::class, [
            'action' => 'platform.support_access.started',
        ]);
    }

    public function test_super_admin_gets_full_owner_rights_without_approval_or_reason(): void
    {
        $superAdmin = User::factory()->create(['is_platform_super_admin' => true]);
        $academy = Academy::factory()->create();

        $this->actingAs($superAdmin)
            ->post(route('platform.academies.support-access.start', $academy))
            ->assertRedirect(route('dashboard'))
            ->assertInertiaFlash('toast.message', 'Support Mode started.');

        $this->actingAs($superAdmin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('academy.id', $academy->id)
                ->where('academy.can_manage', true)
                ->where('academy.role_label', 'Support Mode · Owner/Admin')
                ->where('support_access.academy_name', $academy->name)
            );

        $this->actingAs($superAdmin)
            ->get(route('academy.team.index'))
            ->assertOk();

        $this->assertDatabaseMissing(AcademyMembership::class, [
            'academy_id' => $academy->id,
            'user_id' => $superAdmin->id,
        ]);

        $this->assertDatabaseHas(AuditLog::class, [
            'academy_id' => $academy->id,
            'actor_user_id' => $superAdmin->id,
            'action' => 'platform.support_access.started',
        ]);
    }

    public function test_support_mode_can_be_exited_and_is_audited(): void
    {
        $superAdmin = User::factory()->create(['is_platform_super_admin' => true]);
        $academy = Academy::factory()->create();

        $this->actingAs($superAdmin)
            ->post(route('platform.academies.support-access.start', $academy));

        $this->actingAs($superAdmin)
            ->delete(route('platform.support-access.destroy'))
            ->assertRedirect(route('platform.academies.index'))
            ->assertInertiaFlash('toast.message', 'Support Mode ended.');

        $this->assertDatabaseHas(AuditLog::class, [
            'academy_id' => $academy->id,
            'actor_user_id' => $superAdmin->id,
            'action' => 'platform.support_access.ended',
        ]);
    }

    public function test_support_mode_has_no_time_limit(): void
    {
        $superAdmin = User::factory()->create(['is_platform_super_admin' => true]);
        $academy = Academy::factory()->create();

        $this->actingAs($superAdmin)
            ->withSession(['platform_support_access' => [
                'academy_id' => $academy->id,
                'expires_at' => now()->subMinute()->toIso8601String(),
            ]])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('academy.id', $academy->id)
                ->where('academy.can_manage', true)
            )
            ->assertSessionHas('platform_support_access');
    }
}
