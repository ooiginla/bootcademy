<?php

namespace App\Commerce;

use App\Enums\AcademyInvoicePurpose;
use App\Enums\AcademyInvoiceStatus;
use App\Models\Academy;
use App\Models\AcademyInvoice;
use App\Models\AcademyInvoiceInstallment;
use App\Models\Application;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\StoreOrder;
use App\Platform\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApplyCoupon
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param list<array{invoice_id:int, installment_id:int, amount_minor:int, label:string}> $paymentAllocations */
    public function toApplication(
        Academy $academy,
        Application $application,
        string $code,
        string $customerEmail,
        array $paymentAllocations,
        Request $request,
    ): CouponRedemption {
        abort_unless($application->academy_id === $academy->id, 404);

        return DB::transaction(function () use ($academy, $application, $code, $customerEmail, $paymentAllocations, $request): CouponRedemption {
            $existing = CouponRedemption::query()->where('academy_id', $academy->id)
                ->where('application_id', $application->id)->lockForUpdate()->first();
            if ($existing !== null) {
                if ($existing->coupon->code !== $this->normalize($code)) {
                    throw ValidationException::withMessages(['coupon_code' => 'A different coupon has already been applied to this application.']);
                }

                return $existing;
            }

            $coupon = $this->coupon($academy, $code, $customerEmail);
            $invoiceIds = collect($paymentAllocations)->pluck('invoice_id')->map(fn ($id): int => (int) $id)->all();
            $invoices = AcademyInvoice::query()->where('academy_id', $academy->id)->whereIn('id', $invoiceIds)
                ->lockForUpdate()->get()->keyBy('id');
            $eligibleAllocations = collect($paymentAllocations)->filter(function (array $allocation) use ($coupon, $invoices): bool {
                $invoice = $invoices->get($allocation['invoice_id']);
                if (! $invoice instanceof AcademyInvoice) {
                    return false;
                }
                $scope = $invoice->academy_cycle_fee_id !== null
                    ? 'cycle_fees'
                    : ($invoice->purpose === AcademyInvoicePurpose::Application ? 'application_fee' : 'programme_fee');

                return in_array($scope, $coupon->scopes(), true);
            })->values();
            $eligible = (int) $eligibleAllocations->sum('amount_minor');
            $discount = $this->discount($coupon, $eligible, $academy->currency);
            $remaining = $discount;
            $discountAllocations = [];

            foreach ($eligibleAllocations as $allocation) {
                if ($remaining === 0) {
                    break;
                }
                $invoice = $invoices->get($allocation['invoice_id']);
                $installment = AcademyInvoiceInstallment::query()->where('academy_id', $academy->id)
                    ->where('academy_invoice_id', $invoice->id)->whereKey($allocation['installment_id'])
                    ->lockForUpdate()->firstOrFail();
                $part = min($remaining, (int) $allocation['amount_minor']);
                $installment->increment('coupon_discount_minor', $part);
                $invoice->increment('coupon_discount_minor', $part);
                $invoice->refresh();
                if ($invoice->balanceMinor() === 0) {
                    $invoice->update(['status' => AcademyInvoiceStatus::Settled]);
                }
                $discountAllocations[] = [
                    'invoice_id' => $invoice->id,
                    'installment_id' => $installment->id,
                    'amount_minor' => $part,
                ];
                $remaining -= $part;
            }
            if ($remaining !== 0) {
                throw ValidationException::withMessages(['coupon_code' => 'The coupon could not be allocated across the selected fees.']);
            }

            $redemption = CouponRedemption::query()->create([
                'academy_id' => $academy->id, 'coupon_id' => $coupon->id, 'application_id' => $application->id,
                'customer_key' => $this->customerKey($customerEmail), 'currency' => $academy->currency,
                'eligible_amount_minor' => $eligible, 'discount_minor' => $discount,
                'allocations' => $discountAllocations, 'status' => 'applied', 'applied_at' => now(),
            ]);
            $this->audit->record('academy.coupon.applied', null, $academy, $redemption, [
                'coupon_id' => $coupon->id, 'application_id' => $application->id, 'discount_minor' => $discount,
            ], $request);

            return $redemption;
        });
    }

    public function toStoreOrder(
        Academy $academy,
        StoreOrder $order,
        string $code,
        string $customerEmail,
        Request $request,
    ): CouponRedemption {
        abort_unless($order->academy_id === $academy->id, 404);

        return DB::transaction(function () use ($academy, $order, $code, $customerEmail, $request): CouponRedemption {
            $existing = CouponRedemption::query()->where('academy_id', $academy->id)
                ->where('store_order_id', $order->id)->lockForUpdate()->first();
            if ($existing !== null) {
                return $existing;
            }
            $coupon = $this->coupon($academy, $code, $customerEmail);
            if (! in_array('store', $coupon->scopes(), true)) {
                throw ValidationException::withMessages(['coupon_code' => 'This coupon does not apply to store orders.']);
            }
            $discount = $this->discount($coupon, $order->subtotal_minor, $order->currency);
            $order->update([
                'discount_minor' => $discount,
                'total_minor' => max(0, $order->subtotal_minor + $order->delivery_minor - $discount),
            ]);
            if ($order->total_minor === 0) {
                $order->update(['payment_status' => 'paid', 'paid_at' => now()]);
            }
            $redemption = CouponRedemption::query()->create([
                'academy_id' => $academy->id, 'coupon_id' => $coupon->id, 'store_order_id' => $order->id,
                'customer_key' => $this->customerKey($customerEmail), 'currency' => $order->currency,
                'eligible_amount_minor' => $order->subtotal_minor, 'discount_minor' => $discount,
                'allocations' => [['store_order_id' => $order->id, 'amount_minor' => $discount]],
                'status' => 'applied', 'applied_at' => now(),
            ]);
            if ($order->total_minor === 0) {
                $redemption->update(['status' => 'redeemed', 'redeemed_at' => now()]);
            }
            $this->audit->record('academy.coupon.applied', null, $academy, $redemption, [
                'coupon_id' => $coupon->id, 'store_order_id' => $order->id, 'discount_minor' => $discount,
            ], $request);

            return $redemption;
        });
    }

    private function coupon(Academy $academy, string $code, string $customerEmail): Coupon
    {
        $coupon = Coupon::query()->where('academy_id', $academy->id)->where('code', $this->normalize($code))
            ->lockForUpdate()->first();
        if ($coupon === null || ! $coupon->is_active) {
            throw ValidationException::withMessages(['coupon_code' => 'This coupon code is invalid or inactive.']);
        }
        $today = now()->toDateString();
        $startsOn = $coupon->startsOnDate();
        $endsOn = $coupon->endsOnDate();
        if (($startsOn !== null && $today < $startsOn->toDateString())
            || ($endsOn !== null && $today > $endsOn->toDateString())) {
            throw ValidationException::withMessages(['coupon_code' => 'This coupon is not valid today.']);
        }
        $redemptions = $coupon->redemptions()->whereIn('status', ['applied', 'redeemed']);
        if ($coupon->max_redemptions !== null && (clone $redemptions)->count() >= $coupon->max_redemptions) {
            throw ValidationException::withMessages(['coupon_code' => 'This coupon has reached its redemption limit.']);
        }
        if ($coupon->per_customer_limit !== null && (clone $redemptions)
            ->where('customer_key', $this->customerKey($customerEmail))->count() >= $coupon->per_customer_limit) {
            throw ValidationException::withMessages(['coupon_code' => 'This coupon has already been used the maximum number of times for this email address.']);
        }

        return $coupon;
    }

    private function discount(Coupon $coupon, int $eligible, string $currency): int
    {
        if ($eligible <= 0) {
            throw ValidationException::withMessages(['coupon_code' => 'This coupon does not apply to the selected items or fees.']);
        }
        if ($coupon->currency !== $currency) {
            throw ValidationException::withMessages(['coupon_code' => 'This coupon is not available for this currency.']);
        }
        if ($coupon->minimum_amount_minor !== null && $eligible < $coupon->minimum_amount_minor) {
            throw ValidationException::withMessages(['coupon_code' => 'The selected amount is below this coupon’s minimum spend.']);
        }
        $discount = $coupon->discount_type === 'percentage'
            ? intdiv($eligible * (int) $coupon->percentage_basis_points, 10000)
            : (int) $coupon->fixed_amount_minor;

        return min($eligible, max(1, $discount));
    }

    private function normalize(string $code): string
    {
        return strtoupper(trim($code));
    }

    private function customerKey(string $email): string
    {
        return hash('sha256', strtolower(trim($email)));
    }
}
