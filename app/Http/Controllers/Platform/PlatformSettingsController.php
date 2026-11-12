<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\UpdatePlatformSettingsRequest;
use App\Models\PlatformSetting;
use App\Platform\AuditLogger;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PlatformSettingsController extends Controller
{
    public function edit(): Response
    {
        $settings = PlatformSetting::current();

        return Inertia::render('platform/settings', [
            'settings' => [
                'paystack_percentage' => number_format($settings->paystack_fee_basis_points / 100, 2, '.', ''),
                'paystack_fixed_fee' => Money::fromMinor($settings->paystack_fee_fixed_minor),
                'paystack_cap' => $settings->paystack_fee_cap_minor === null ? '' : Money::fromMinor($settings->paystack_fee_cap_minor),
                'paystack_waiver_threshold' => $settings->paystack_fee_waiver_threshold_minor === null ? '' : Money::fromMinor($settings->paystack_fee_waiver_threshold_minor),
                'platform_percentage' => number_format($settings->platform_fee_basis_points / 100, 2, '.', ''),
                'platform_fixed_fee' => Money::fromMinor($settings->platform_fee_fixed_minor),
            ],
            'paystack_configured' => filled(config('services.paystack.secret_key')),
        ]);
    }

    public function update(UpdatePlatformSettingsRequest $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validated();
        $settings = PlatformSetting::current();
        $before = $settings->only($settings->getFillable());
        $settings->update([
            'paystack_fee_basis_points' => (int) round((float) $data['paystack_percentage'] * 100),
            'paystack_fee_fixed_minor' => Money::toMinor((string) $data['paystack_fixed_fee']),
            'paystack_fee_cap_minor' => $data['paystack_cap'] === null ? null : Money::toMinor((string) $data['paystack_cap']),
            'paystack_fee_waiver_threshold_minor' => $data['paystack_waiver_threshold'] === null ? null : Money::toMinor((string) $data['paystack_waiver_threshold']),
            'platform_fee_basis_points' => (int) round((float) $data['platform_percentage'] * 100),
            'platform_fee_fixed_minor' => Money::toMinor((string) $data['platform_fixed_fee']),
        ]);
        $audit->record('platform.settings.online-fees.updated', $request->user(), null, $settings, [
            'before' => $before,
            'after' => $settings->only($settings->getFillable()),
        ], $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Online payment fees updated.']);

        return back();
    }
}
