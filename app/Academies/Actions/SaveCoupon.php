<?php

namespace App\Academies\Actions;

use App\Models\Academy;
use App\Models\Coupon;
use App\Models\User;
use App\Platform\AuditLogger;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SaveCoupon
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param array<string, mixed> $data */
    public function handle(Academy $academy, User $actor, array $data, Request $request, ?Coupon $coupon = null): Coupon
    {
        return DB::transaction(function () use ($academy, $actor, $data, $request, $coupon): Coupon {
            $coupon ??= new Coupon(['academy_id' => $academy->id, 'created_by_user_id' => $actor->id]);
            abort_unless($coupon->academy_id === $academy->id, 404);
            $before = $coupon->exists ? $coupon->only(['code', 'name', 'discount_type', 'is_active']) : null;
            $coupon->fill([
                'code' => $data['code'], 'name' => $data['name'], 'discount_type' => $data['discount_type'],
                'percentage_basis_points' => $data['discount_type'] === 'percentage' ? (int) round((float) $data['percentage'] * 100) : null,
                'fixed_amount_minor' => $data['discount_type'] === 'fixed' ? Money::toMinor((string) $data['fixed_amount']) : null,
                'currency' => $academy->currency, 'applies_to' => $data['applies_to'],
                'minimum_amount_minor' => $data['minimum_amount'] === null ? null : Money::toMinor((string) $data['minimum_amount']),
                'starts_on' => $data['starts_on'], 'ends_on' => $data['ends_on'],
                'max_redemptions' => $data['maximum_redemptions'], 'per_customer_limit' => $data['per_customer_limit'],
                'is_active' => $data['is_active'],
            ]);
            $coupon->save();
            $this->audit->record($before === null ? 'academy.coupon.created' : 'academy.coupon.updated', $actor, $academy, $coupon, [
                'before' => $before,
                'after' => $coupon->only(['code', 'name', 'discount_type', 'is_active']),
            ], $request);

            return $coupon->fresh();
        });
    }
}
