<?php

namespace App\Http\Controllers\Platform;

use App\Academies\Actions\UpdateAcademyTheme;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\UpdateAcademyPaystackFeesRequest;
use App\Http\Requests\Platform\UpdateAcademyThemeRequest;
use App\Models\Academy;
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
        $academies = Academy::query()->withCount('memberships');

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
                'paystack_fees' => [
                    'percentage' => number_format($academy->paystack_fee_basis_points / 100, 2, '.', ''),
                    'fixed_fee' => Money::fromMinor($academy->paystack_fee_fixed_minor),
                    'cap' => $academy->paystack_fee_cap_minor === null ? '' : Money::fromMinor($academy->paystack_fee_cap_minor),
                    'waiver_threshold' => $academy->paystack_fee_waiver_threshold_minor === null ? '' : Money::fromMinor($academy->paystack_fee_waiver_threshold_minor),
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
        $before = $academy->only([
            'paystack_fee_basis_points', 'paystack_fee_fixed_minor', 'paystack_fee_cap_minor',
            'paystack_fee_waiver_threshold_minor',
        ]);
        $academy->update([
            'paystack_fee_basis_points' => (int) round((float) $data['percentage'] * 100),
            'paystack_fee_fixed_minor' => Money::toMinor((string) $data['fixed_fee']),
            'paystack_fee_cap_minor' => ($data['cap'] ?? null) === null ? null : Money::toMinor((string) $data['cap']),
            'paystack_fee_waiver_threshold_minor' => ($data['waiver_threshold'] ?? null) === null ? null : Money::toMinor((string) $data['waiver_threshold']),
        ]);
        $audit->record('platform.academy.paystack-fees.updated', $request->user(), $academy, $academy, [
            'before' => $before,
            'after' => $academy->only(array_keys($before)),
        ], $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Paystack fee settings updated.']);

        return back();
    }
}
