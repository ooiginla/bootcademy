<?php

namespace App\Http\Controllers\Academies;

use App\Academies\AcademyContext;
use App\Http\Controllers\Controller;
use App\Models\AcademyApiKey;
use App\Platform\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AcademyApiKeyController extends Controller
{
    public function index(Request $request, AcademyContext $context): Response
    {
        abort_unless($context->canManageAcademy(), 403);

        return Inertia::render('academies/api-integrations', [
            'api_keys' => $context->academy()->apiKeys()->latest()->get()->map(fn (AcademyApiKey $key): array => [
                'id' => $key->id, 'name' => $key->name, 'prefix' => $key->prefix,
                'last_used_at' => $key->last_used_at?->toIso8601String(),
                'created_at' => $key->created_at?->toIso8601String(),
                'revoked_at' => $key->revoked_at?->toIso8601String(),
            ]),
            'new_api_secret' => $request->session()->pull('new_api_secret'),
        ]);
    }

    public function store(Request $request, AcademyContext $context, AuditLogger $audit): RedirectResponse
    {
        abort_unless($context->canManageAcademy(), 403);
        $validated = $request->validate(['name' => ['required', 'string', 'max:100']]);
        $secret = 'aca_'.Str::lower(Str::random(72));
        $key = $context->academy()->apiKeys()->create([
            'created_by_user_id' => $request->user()->id,
            'name' => trim($validated['name']),
            'prefix' => substr($secret, 0, 12),
            'secret_hash' => hash('sha256', $secret),
        ]);
        $request->session()->flash('new_api_secret', $secret);
        $audit->record('academy.api_key.created', $request->user(), $context->academy(), $key, [
            'name' => $key->name, 'prefix' => $key->prefix,
        ], $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'API key created. Copy it now; it will not be shown again.']);

        return to_route('academy.api-keys.index');
    }

    public function revoke(Request $request, AcademyApiKey $apiKey, AcademyContext $context, AuditLogger $audit): RedirectResponse
    {
        abort_unless($context->canManageAcademy(), 403);
        abort_unless($apiKey->academy_id === $context->id(), 404);
        if ($apiKey->revoked_at === null) {
            $apiKey->forceFill(['revoked_at' => now()])->save();
            $audit->record('academy.api_key.revoked', $request->user(), $context->academy(), $apiKey, [
                'name' => $apiKey->name, 'prefix' => $apiKey->prefix,
            ], $request);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => 'API key revoked.']);

        return back();
    }
}
