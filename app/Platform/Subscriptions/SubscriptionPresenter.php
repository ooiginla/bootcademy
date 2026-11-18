<?php

namespace App\Platform\Subscriptions;

use App\Enums\BillingInterval;
use App\Models\Academy;
use App\Models\AcademyPlanPrice;
use App\Models\AcademySubscriptionPeriod;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\SubscriptionPayment;
use App\Payments\PaystackFeeCalculator;
use App\Platform\AcademyEntitlements;
use Carbon\CarbonInterface;

/** Shapes plans, periods and payments for the subscription pages. */
class SubscriptionPresenter
{
    public function __construct(private readonly PaystackFeeCalculator $fees) {}

    /** @return array<string, mixed>|null */
    public function period(?AcademySubscriptionPeriod $period): ?array
    {
        if ($period === null) {
            return null;
        }

        return [
            'id' => $period->id,
            'kind' => $period->kind,
            'plan_name' => $period->plan->name,
            'billing_interval' => $period->billing_interval?->value,
            'billing_interval_label' => $period->billing_interval?->label(),
            'starts_at' => $period->starts_at->toIso8601String(),
            'ends_at' => $period->ends_at->toIso8601String(),
            'note' => $period->note,
        ];
    }

    /** Where a period paid for now would start: when paid time runs out, or now. */
    public function nextPeriodStart(AcademyEntitlements $entitlements): CarbonInterface
    {
        $paidThrough = $entitlements->paidThrough();

        return $paidThrough !== null && $paidThrough->isFuture() ? $paidThrough : now();
    }

    /**
     * Active plans with the prices this academy pays and the online payment fee on each.
     *
     * @return list<array<string, mixed>>
     */
    public function plans(Academy $academy, ?int $currentPlanId): array
    {
        $overrides = AcademyPlanPrice::query()->where('academy_id', $academy->id)->get()->keyBy('plan_id');

        return array_values(Plan::query()->with('features')->where('is_active', true)->orderBy('sort_order')->get()
            ->map(function (Plan $plan) use ($overrides, $currentPlanId): array {
                $override = $overrides->get($plan->id);
                $prices = [];
                foreach (BillingInterval::cases() as $interval) {
                    $amount = (int) ($override ?? $plan)->getAttribute($interval->priceColumn());
                    $fee = $this->fees->subscriptionBreakdown($amount)['surcharge'];
                    $prices[$interval->value] = [
                        'amount_minor' => $amount,
                        'list_amount_minor' => (int) $plan->getAttribute($interval->priceColumn()),
                        'fee_minor' => $fee,
                        'total_minor' => $amount + $fee,
                    ];
                }

                return [
                    ...$this->planDetails($plan),
                    'prices' => $prices,
                    'has_custom_price' => $override !== null,
                    'is_current' => $plan->id === $currentPlanId,
                ];
            })->all());
    }

    /**
     * Active plans at list price, for the public pricing section.
     *
     * @return list<array<string, mixed>>
     */
    public function publicPlans(): array
    {
        return array_values(Plan::query()->with('features')->where('is_active', true)->orderBy('sort_order')->get()
            ->map(fn (Plan $plan): array => [
                ...$this->planDetails($plan),
                'prices' => collect(BillingInterval::cases())
                    ->mapWithKeys(fn (BillingInterval $interval): array => [$interval->value => (int) $plan->getAttribute($interval->priceColumn())])
                    ->all(),
            ])->all());
    }

    /** @return array{days: int, plan_name: string|null} */
    public function trial(): array
    {
        $settings = PlatformSetting::current();

        return [
            'days' => $settings->trial_days,
            'plan_name' => $settings->trial_plan_id ? Plan::query()->whereKey($settings->trial_plan_id)->value('name') : null,
        ];
    }

    /** @return array<string, mixed> */
    public function planDetails(Plan $plan): array
    {
        return [
            'id' => $plan->id,
            'code' => $plan->code,
            'name' => $plan->name,
            'description' => $plan->description,
            'currency' => $plan->currency,
            'is_recommended' => $plan->is_recommended,
            'active_player_limit' => $plan->active_player_limit,
            'branch_limit' => $plan->branch_limit,
            'staff_account_limit' => $plan->staff_account_limit,
            'migration_support' => $plan->migration_support,
            'support_level' => $plan->support_level,
            'flags' => $plan->flags(),
        ];
    }

    /** @return array{included: list<string>, flags: array<string, string>} */
    public function comparison(): array
    {
        return [
            'included' => array_values((array) config('plans.included')),
            'flags' => (array) config('plans.flags'),
        ];
    }

    /** @return array<string, mixed> */
    public function payment(SubscriptionPayment $payment): array
    {
        return [
            'id' => $payment->id,
            'reference' => $payment->reference,
            'plan_name' => $payment->plan->name,
            'billing_interval_label' => $payment->billing_interval->label(),
            'currency' => $payment->currency,
            'amount_minor' => $payment->amount_minor,
            'surcharge_minor' => $payment->surcharge_minor,
            'platform_fee_minor' => $payment->platform_fee_minor,
            'charged_amount_minor' => $payment->charged_amount_minor,
            'status' => $payment->status,
            'created_at' => $payment->created_at?->toIso8601String(),
            'verified_at' => $payment->verified_at?->toIso8601String(),
            'period_starts_at' => $payment->period?->starts_at->toIso8601String(),
            'period_ends_at' => $payment->period?->ends_at->toIso8601String(),
        ];
    }
}
