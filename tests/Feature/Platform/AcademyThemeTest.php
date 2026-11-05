<?php

namespace Tests\Feature\Platform;

use App\Models\Academy;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademyThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_super_admin_can_update_an_academy_theme(): void
    {
        $admin = User::factory()->create(['is_platform_super_admin' => true]);
        $academy = Academy::factory()->create();

        $this->actingAs($admin)
            ->patch(route('platform.academies.branding.update', $academy), [
                'primary_color' => '#1a2b3c',
                'secondary_color' => '#f4e3d2',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas(Academy::class, [
            'id' => $academy->id,
            'primary_color' => '#1A2B3C',
            'secondary_color' => '#F4E3D2',
        ]);
        $this->assertDatabaseHas(AuditLog::class, [
            'academy_id' => $academy->id,
            'actor_user_id' => $admin->id,
            'action' => 'academy.theme.updated',
        ]);
    }

    public function test_regular_user_cannot_access_platform_theme_controls(): void
    {
        $user = User::factory()->create();
        $academy = Academy::factory()->create();

        $this->actingAs($user)
            ->get(route('platform.academies.branding.edit', $academy))
            ->assertForbidden();

        $this->actingAs($user)
            ->patch(route('platform.academies.branding.update', $academy), [
                'primary_color' => '#123456',
                'secondary_color' => '#ABCDEF',
            ])
            ->assertForbidden();

        $this->assertNull($academy->fresh()->primary_color);
    }

    public function test_invalid_theme_values_are_rejected_without_audit_or_update(): void
    {
        $admin = User::factory()->create(['is_platform_super_admin' => true]);
        $academy = Academy::factory()->create();

        $this->actingAs($admin)
            ->patch(route('platform.academies.branding.update', $academy), [
                'primary_color' => 'red',
                'secondary_color' => '#FFFF',
            ])
            ->assertSessionHasErrors(['primary_color', 'secondary_color']);

        $this->assertNull($academy->fresh()->primary_color);
        $this->assertDatabaseCount(AuditLog::class, 0);
    }
}
