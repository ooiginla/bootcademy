<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Platform\AuditLogger;
use Illuminate\Console\Command;

class SetPlatformSuperAdmin extends Command
{
    protected $signature = 'platform:super-admin {email : Existing user email} {--revoke : Remove platform access}';

    protected $description = 'Grant or revoke the Platform Super Admin role for an existing user';

    public function handle(AuditLogger $audit): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('No user exists with that email address.');

            return self::FAILURE;
        }

        $enabled = ! $this->option('revoke');
        $user->update(['is_platform_super_admin' => $enabled]);
        $audit->record(
            $enabled ? 'platform.super_admin.granted' : 'platform.super_admin.revoked',
            null,
            null,
            $user,
            ['target_user_id' => $user->id],
        );

        $this->info($enabled ? 'Platform Super Admin access granted.' : 'Platform Super Admin access revoked.');

        return self::SUCCESS;
    }
}
