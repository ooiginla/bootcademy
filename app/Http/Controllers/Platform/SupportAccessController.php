<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Academy;
use App\Platform\AuditLogger;
use App\Platform\SupportAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupportAccessController extends Controller
{
    public function show(Academy $academy): Response
    {
        return Inertia::render('platform/academies/support-access', [
            'managedAcademy' => [
                'id' => $academy->id,
                'name' => $academy->name,
            ],
        ]);
    }

    public function start(
        Request $request,
        Academy $academy,
        SupportAccess $supportAccess,
        AuditLogger $audit,
    ): RedirectResponse {
        $supportAccess->start($request, $request->user(), $academy);

        $audit->record('platform.support_access.started', $request->user(), $academy, $academy, request: $request);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Support Mode started.']);

        return to_route('dashboard');
    }

    public function destroy(
        Request $request,
        SupportAccess $supportAccess,
        AuditLogger $audit,
    ): RedirectResponse {
        $academy = $supportAccess->resolve($request);
        abort_unless($academy !== null, 404);

        $audit->record('platform.support_access.ended', $request->user(), $academy, $academy, request: $request);
        $supportAccess->stop($request);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Support Mode ended.']);

        return to_route('platform.academies.index');
    }
}
