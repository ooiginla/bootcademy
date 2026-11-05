<?php

namespace Tests\Feature\Platform;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetPlatformSuperAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_access_can_be_explicitly_granted_and_revoked(): void
    {
        $user = User::factory()->create();

        $this->artisan('platform:super-admin', ['email' => $user->email])
            ->expectsOutput('Platform Super Admin access granted.')
            ->assertSuccessful();
        $this->assertTrue($user->fresh()->is_platform_super_admin);

        $this->artisan('platform:super-admin', ['email' => $user->email, '--revoke' => true])
            ->expectsOutput('Platform Super Admin access revoked.')
            ->assertSuccessful();
        $this->assertFalse($user->fresh()->is_platform_super_admin);
        $this->assertDatabaseCount(AuditLog::class, 2);
    }

    public function test_platform_access_command_fails_for_unknown_user(): void
    {
        $this->artisan('platform:super-admin', ['email' => 'missing@example.test'])
            ->expectsOutput('No user exists with that email address.')
            ->assertFailed();
    }
}
