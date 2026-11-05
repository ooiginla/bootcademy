<?php

namespace App\Http\Controllers;

use App\Http\Requests\RequiredPasswordUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class RequiredPasswordController extends Controller
{
    public function edit(Request $request): Response|RedirectResponse
    {
        if (! $request->user()->must_change_password) {
            return to_route('dashboard');
        }

        return Inertia::render('auth/change-temporary-password', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function update(RequiredPasswordUpdateRequest $request): RedirectResponse
    {
        $request->user()->update([
            'password' => $request->string('password')->toString(),
            'must_change_password' => false,
        ]);
        $request->session()->regenerate();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Your personal password is now set.']);

        return to_route('dashboard');
    }
}
