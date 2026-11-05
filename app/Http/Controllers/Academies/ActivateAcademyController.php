<?php

namespace App\Http\Controllers\Academies;

use App\Enums\MembershipStatus;
use App\Http\Controllers\Controller;
use App\Models\Academy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ActivateAcademyController extends Controller
{
    public function __invoke(Request $request, Academy $academy): RedirectResponse
    {
        $authorized = $request->user()->academyMemberships()
            ->whereBelongsTo($academy)
            ->where('status', MembershipStatus::Active->value)
            ->exists();

        abort_unless($authorized, 404);

        $request->session()->put('active_academy_id', $academy->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Switched to '.$academy->name.'.']);

        return to_route('dashboard');
    }
}
