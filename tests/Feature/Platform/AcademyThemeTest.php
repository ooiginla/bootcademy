<?php

namespace Tests\Feature\Platform;

use App\Models\Academy;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
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

    public function test_platform_super_admin_can_configure_paystack_customer_fees_per_academy(): void
    {
        $admin = User::factory()->create(['is_platform_super_admin' => true]);
        $academy = Academy::factory()->create();

        $this->actingAs($admin)
            ->get(route('platform.academies.branding.edit', $academy))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('managedAcademy.paystack_fees.percentage', '1.50')
                ->where('managedAcademy.paystack_fees.fixed_fee', '100.00'));

        $this->actingAs($admin)
            ->patch(route('platform.academies.paystack-fees.update', $academy), [
                'percentage' => '2.25',
                'fixed_fee' => '150.00',
                'cap' => '2500.00',
                'waiver_threshold' => '3000.00',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas(Academy::class, [
            'id' => $academy->id,
            'paystack_fee_basis_points' => 225,
            'paystack_fee_fixed_minor' => 15000,
            'paystack_fee_cap_minor' => 250000,
            'paystack_fee_waiver_threshold_minor' => 300000,
        ]);
        $this->assertDatabaseHas(AuditLog::class, [
            'academy_id' => $academy->id,
            'actor_user_id' => $admin->id,
            'action' => 'platform.academy.paystack-fees.updated',
        ]);
    }

    public function test_regular_user_cannot_change_paystack_fee_configuration(): void
    {
        $user = User::factory()->create();
        $academy = Academy::factory()->create();

        $this->actingAs($user)
            ->patch(route('platform.academies.paystack-fees.update', $academy), [
                'percentage' => '9.00', 'fixed_fee' => '900.00',
            ])
            ->assertForbidden();

        $this->assertSame(150, $academy->fresh()->paystack_fee_basis_points);
    }
}
