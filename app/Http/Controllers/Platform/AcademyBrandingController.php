<?php

namespace App\Http\Controllers\Platform;

use App\Academies\Actions\UpdateAcademyBankDetails;
use App\Academies\Actions\UpdateAcademyTheme;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\UpdateAcademyPaystackFeesRequest;
use App\Http\Requests\Platform\UpdateAcademyThemeRequest;
use App\Models\Academy;
use App\Models\PlatformSetting;
use App\Platform\AuditLogger;
use App\Support\AccessibleColor;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AcademyBrandingController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search'));
        $academies = Academy::query()->withCount(['memberships', 'players', 'applications']);

        if ($search !== '') {
            $academies->where('name', 'like', '%'.$search.'%');
        }

        return Inertia::render('platform/academies/index', [
            'academies' => $academies
                ->orderBy('name')
                ->paginate(30)
                ->withQueryString()
                ->through(fn (Academy $academy): array => [
                    'id' => $academy->id,
                    'name' => $academy->name,
                    'academy_type' => $academy->academy_type,
                    'memberships_count' => $academy->memberships_count,
                    'players_count' => $academy->players_count,
                    'applications_count' => $academy->applications_count,
                    'theme' => AccessibleColor::theme($academy->primary_color, $academy->secondary_color),
                ]),
            'filters' => ['search' => $search],
        ]);
    }

    public function edit(Academy $academy): Response
    {
        return Inertia::render('platform/academies/branding', [
            'managedAcademy' => [
                'id' => $academy->id,
                'name' => $academy->name,
                'theme' => AccessibleColor::theme($academy->primary_color, $academy->secondary_color),
                'platform_fee' => [
                    'platform_percentage' => $academy->platform_fee_basis_points === null ? '' : number_format($academy->platform_fee_basis_points / 100, 2, '.', ''),
                    'platform_fixed_fee' => $academy->platform_fee_fixed_minor === null ? '' : Money::fromMinor($academy->platform_fee_fixed_minor),
                ],
                'default_platform_fee' => [
                    'percentage' => number_format(PlatformSetting::current()->platform_fee_basis_points / 100, 2, '.', ''),
                    'fixed_fee' => Money::fromMinor(PlatformSetting::current()->platform_fee_fixed_minor),
                ],
                'settlement' => [
                    'bank_name' => $academy->bank_name,
                    'account_name' => $academy->bank_account_name,
                    'account_number_last4' => $academy->bank_account_number === null ? null : substr($academy->bank_account_number, -4),
                    'status' => $academy->paystack_subaccount_status,
                    'subaccount_code' => $academy->paystack_subaccount_code,
                    'error' => $academy->paystack_subaccount_error,
                    'synced_at' => $academy->paystack_subaccount_synced_at?->toIso8601String(),
                    'can_retry' => $academy->bank_code !== null && filled(config('services.paystack.secret_key')),
                ],
            ],
        ]);
    }

    public function update(
        UpdateAcademyThemeRequest $request,
        Academy $academy,
        UpdateAcademyTheme $updateTheme,
    ): RedirectResponse {
        $updateTheme->handle(
            $academy,
            $request->user(),
            $request->string('primary_color')->toString(),
            $request->string('secondary_color')->toString(),
            $request,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Academy theme updated.']);

        return back();
    }

    public function updatePaystackFees(
        UpdateAcademyPaystackFeesRequest $request,
        Academy $academy,
        AuditLogger $audit,
    ): RedirectResponse {
        $data = $request->validated();
        $before = $academy->only(['platform_fee_basis_points', 'platform_fee_fixed_minor']);
        $useDefault = $data['platform_percentage'] === null && $data['platform_fixed_fee'] === null;
        $academy->update([
            'platform_fee_basis_points' => $useDefault ? null : (int) round((float) ($data['platform_percentage'] ?? 0) * 100),
            'platform_fee_fixed_minor' => $useDefault ? null : Money::toMinor((string) ($data['platform_fixed_fee'] ?? '0')),
        ]);
        $audit->record('platform.academy.platform-fee.updated', $request->user(), $academy, $academy, [
            'before' => $before,
            'after' => $academy->only(array_keys($before)),
        ], $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => $useDefault ? 'This academy now uses the default platform fee.' : 'Platform fee override saved.']);

        return back();
    }

    public function syncSubaccount(Request $request, Academy $academy, UpdateAcademyBankDetails $bankDetails): RedirectResponse
    {
        $bankDetails->syncSubaccount($academy, $request->user(), $request);
        $academy->refresh();
        Inertia::flash('toast', $academy->paystack_subaccount_error === null
            ? ['type' => 'success', 'message' => 'Paystack settlement account is up to date.']
            : ['type' => 'error', 'message' => $academy->paystack_subaccount_error]);

        return back();
    }
}
