<?php

namespace App\Http\Controllers\Academies;

use App\Academies\Actions\CreateAcademy;
use App\Enums\MembershipStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Academies\StoreAcademyRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AcademyOnboardingController extends Controller
{
    public function create(): Response|RedirectResponse
    {
        if (request()->user()->activeAcademyMemberships()->exists()) {
            return to_route('dashboard');
        }

        if (request()->user()->academyMemberships()
            ->where('status', MembershipStatus::Invited->value)
            ->exists()) {
            return to_route('academy.invitations.index');
        }

        return Inertia::render('academies/onboarding', [
            'defaults' => [
                'timezone' => 'Africa/Lagos',
                'currency' => 'NGN',
            ],
        ]);
    }

    public function store(StoreAcademyRequest $request, CreateAcademy $createAcademy): RedirectResponse
    {
        $validated = $request->validated();
        $academy = $createAcademy->handle($request->user(), [
            'name' => $validated['name'],
            'academy_type' => $validated['academy_type'],
            'timezone' => $validated['timezone'],
            'currency' => $validated['currency'],
        ]);
        $request->session()->put('active_academy_id', $academy->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Your academy workspace is ready.']);

        return to_route('dashboard');
    }
}
