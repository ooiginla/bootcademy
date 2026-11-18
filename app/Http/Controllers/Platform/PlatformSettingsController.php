<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\UpdatePlatformSettingsRequest;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Payments\Contracts\PaymentProvider;
use App\Platform\AuditLogger;
use App\Platform\Subscriptions\UpdatePlatformSettlementAccount;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class PlatformSettingsController extends Controller
{
    public function edit(PaymentProvider $provider): Response
    {
        $settings = PlatformSetting::current();
        $paystack = filled(config('services.paystack.secret_key'));

        return Inertia::render('platform/settings', [
            'settings' => [
                'paystack_percentage' => number_format($settings->paystack_fee_basis_points / 100, 2, '.', ''),
                'paystack_fixed_fee' => Money::fromMinor($settings->paystack_fee_fixed_minor),
                'paystack_cap' => $settings->paystack_fee_cap_minor === null ? '' : Money::fromMinor($settings->paystack_fee_cap_minor),
                'paystack_waiver_threshold' => $settings->paystack_fee_waiver_threshold_minor === null ? '' : Money::fromMinor($settings->paystack_fee_waiver_threshold_minor),
                'platform_percentage' => number_format($settings->platform_fee_basis_points / 100, 2, '.', ''),
                'platform_fixed_fee' => Money::fromMinor($settings->platform_fee_fixed_minor),
            ],
            'paystack_configured' => $paystack,
            'trial' => [
                'trial_plan_id' => $settings->trial_plan_id,
                'post_trial_plan_id' => $settings->post_trial_plan_id,
                'trial_days' => $settings->trial_days,
            ],
            'plans' => Plan::query()->orderBy('sort_order')->get(['id', 'name'])->map(fn (Plan $plan): array => [
                'id' => $plan->id, 'name' => $plan->name,
            ])->values(),
            'bank_details' => [
                'bank_code' => $settings->bank_code,
                'bank_name' => $settings->bank_name,
                'account_name' => $settings->bank_account_name,
                'account_number' => $settings->bank_account_number,
            ],
            'settlement' => [
                'status' => $settings->paystack_subaccount_status,
                'subaccount_code' => $settings->paystack_subaccount_code,
                'error' => $settings->paystack_subaccount_error,
                'synced_at' => $settings->paystack_subaccount_synced_at?->toIso8601String(),
                'accepts_online_payments' => $paystack && $settings->hasActiveSubaccount(),
            ],
            'banks' => $paystack ? $this->banks($provider) : [],
        ]);
    }

    public function updateTrial(Request $request, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'trial_plan_id' => ['required', 'integer', Rule::exists('plans', 'id')],
            'post_trial_plan_id' => ['required', 'integer', Rule::exists('plans', 'id')],
            'trial_days' => ['required', 'integer', 'min:1', 'max:365'],
        ]);
        $settings = PlatformSetting::current();
        $before = $settings->only(['trial_plan_id', 'post_trial_plan_id', 'trial_days']);
        $settings->update($validated);
        $audit->record('platform.settings.trial.updated', $request->user(), null, $settings, [
            'before' => $before, 'after' => $settings->only(array_keys($before)),
        ], $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Free trial settings updated. They apply to academies that sign up from now on.']);

        return back();
    }

    public function updateSettlementAccount(Request $request, UpdatePlatformSettlementAccount $update): RedirectResponse
    {
        abort_unless(filled(config('services.paystack.secret_key')), 422, 'Paystack is not configured.');
        $request->merge([
            'bank_code' => trim((string) $request->input('bank_code')) ?: null,
            'bank_account_number' => trim((string) $request->input('bank_account_number')) ?: null,
        ]);
        $validated = $request->validate([
            'bank_code' => ['nullable', 'required_with:bank_account_number', 'string', 'max:20', 'regex:/^[A-Za-z0-9-]+$/'],
            'bank_account_number' => ['nullable', 'required_with:bank_code', 'string', 'digits:10'],
        ], ['bank_account_number.digits' => 'Enter the 10-digit account number.']);
        $settings = $update->handle($validated['bank_code'] ?? null, $validated['bank_account_number'] ?? null, $request->user(), $request);

        if ($settings->bank_code !== null && ! $settings->hasActiveSubaccount()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Bank details saved, but Paystack did not accept the settlement account. Subscription payments stay in your main Paystack balance until it does.']);
        } else {
            Inertia::flash('toast', ['type' => 'success', 'message' => $settings->bank_code === null ? 'Settlement account removed.' : 'Settlement account saved.']);
        }

        return back();
    }

    /** @return list<array{code: string, name: string}> */
    private function banks(PaymentProvider $provider): array
    {
        try {
            return $provider->banks();
        } catch (RuntimeException) {
            return [];
        }
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
