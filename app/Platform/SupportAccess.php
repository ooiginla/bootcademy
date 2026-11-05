<?php

namespace App\Platform;

use App\Models\Academy;
use App\Models\User;
use Illuminate\Http\Request;

class SupportAccess
{
    private const SESSION_KEY = 'platform_support_access';

    public function resolve(Request $request): ?Academy
    {
        $user = $request->user();
        $data = $request->session()->get(self::SESSION_KEY);

        if (! $user?->is_platform_super_admin || ! is_array($data)) {
            return null;
        }

        $academyId = $data['academy_id'] ?? null;

        if (! is_int($academyId)) {
            $this->stop($request);

            return null;
        }

        $academy = Academy::query()->find($academyId);

        if (! $academy) {
            $this->stop($request);

            return null;
        }

        return $academy;
    }

    public function start(Request $request, User $user, Academy $academy): void
    {
        abort_unless($user->is_platform_super_admin, 403);

        $request->session()->put(self::SESSION_KEY, [
            'academy_id' => $academy->id,
        ]);
    }

    public function stop(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }

    /** @return array{academy_id: int}|null */
    public function details(Request $request): ?array
    {
        $academy = $this->resolve($request);
        $data = $request->session()->get(self::SESSION_KEY);

        if (! $academy || ! is_array($data)) {
            return null;
        }

        return [
            'academy_id' => $academy->id,
        ];
    }
}
