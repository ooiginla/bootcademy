<?php

namespace App\Http\Controllers\Academies;

use App\Academies\AcademyContext;
use App\Academies\Actions\SaveCoupon;
use App\Http\Controllers\Controller;
use App\Http\Requests\Academies\SaveCouponRequest;
use App\Models\Coupon;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CouponController extends Controller
{
    public function index(AcademyContext $context): Response
    {
        abort_unless($context->canManageFinance(), 403);
        $academy = $context->academy();

        return Inertia::render('academies/coupons', [
            'currency' => $academy->currency,
            'coupons' => $academy->coupons()->latest()->get()->map(fn (Coupon $coupon): array => [
                'id' => $coupon->id, 'code' => $coupon->code, 'name' => $coupon->name,
                'discount_type' => $coupon->discount_type,
                'percentage' => $coupon->percentage_basis_points === null ? null : $coupon->percentage_basis_points / 100,
                'fixed_amount' => $coupon->fixed_amount_minor === null ? null : Money::fromMinor($coupon->fixed_amount_minor),
                'currency' => $coupon->currency, 'applies_to' => $coupon->scopes(),
                'minimum_amount' => $coupon->minimum_amount_minor === null ? null : Money::fromMinor($coupon->minimum_amount_minor),
                'starts_on' => $coupon->startsOnDate()?->toDateString(), 'ends_on' => $coupon->endsOnDate()?->toDateString(),
                'maximum_redemptions' => $coupon->max_redemptions, 'per_customer_limit' => $coupon->per_customer_limit,
                'is_active' => $coupon->is_active, 'redemptions_count' => $coupon->redemptions()->count(),
                'redeemed_count' => $coupon->redemptions()->where('status', 'redeemed')->count(),
            ]),
        ]);
    }

    public function store(SaveCouponRequest $request, AcademyContext $context, SaveCoupon $save): RedirectResponse
    {
        $save->handle($context->academy(), $request->user(), $request->validated(), $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Coupon created.']);

        return back();
    }

    public function update(SaveCouponRequest $request, AcademyContext $context, SaveCoupon $save, Coupon $coupon): RedirectResponse
    {
        $save->handle($context->academy(), $request->user(), $request->validated(), $request, $coupon);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Coupon updated.']);

        return back();
    }
}
