<?php

namespace App\Platform;

use App\Models\Academy;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogger
{
    /** @param array<string, mixed> $metadata */
    public function record(
        string $action,
        ?User $actor,
        ?Academy $academy,
        ?Model $auditable = null,
        array $metadata = [],
        ?Request $request = null,
    ): AuditLog {
        return AuditLog::create([
            'academy_id' => $academy?->id,
            'actor_user_id' => $actor?->id,
            'action' => $action,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'metadata' => $metadata ?: null,
            'ip_address' => $request?->ip(),
        ]);
    }
}
