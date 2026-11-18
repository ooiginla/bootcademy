<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Academy;
use App\Models\AcademySubscriptionPeriod;
use App\Models\SubscriptionPayment;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Which academies have paid the platform, for what plan and period, and what it has earned. */
class TenantSubscriptionController extends Controller
{
    private const array STATUSES = ['verified', 'pending', 'failed', 'expired', 'review_required', 'all'];

    public function __invoke(Request $request): Response
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $status = $validated['status'] ?? 'verified';
        $search = trim((string) ($validated['search'] ?? ''));
        $now = now();

        $verified = SubscriptionPayment::query()->where('status', 'verified');
        $covering = fn () => AcademySubscriptionPeriod::query()->where('starts_at', '<=', $now)->where('ends_at', '>', $now);
        $paidAcademyIds = $covering()->where('kind', '!=', 'trial')->distinct()->pluck('academy_id');
        $trialAcademyIds = $covering()->where('kind', 'trial')->whereNotIn('academy_id', $paidAcademyIds)->distinct()->pluck('academy_id');
        $withAccess = $paidAcademyIds->count() + $trialAcademyIds->count();

        $payments = SubscriptionPayment::query()
            ->with(['academy:id,name', 'plan:id,name', 'period'])
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('reference', 'like', "%{$search}%")
                    ->orWhereHas('academy', fn ($academy) => $academy->where('name', 'like', "%{$search}%"));
            }))
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (SubscriptionPayment $payment): array => [
                'id' => $payment->id,
                'reference' => $payment->reference,
                'academy' => ['id' => $payment->academy_id, 'name' => $payment->academy->name],
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
            ]);

        return Inertia::render('platform/subscriptions', [
            'summary' => [
                'currency' => 'NGN',
                'revenue_minor' => (int) (clone $verified)->sum('amount_minor'),
                'revenue_this_month_minor' => (int) (clone $verified)->where('verified_at', '>=', $now->copy()->startOfMonth())->sum('amount_minor'),
                'platform_fees_minor' => (int) (clone $verified)->sum('platform_fee_minor'),
                'payments_count' => (clone $verified)->count(),
                'paying_academies' => $paidAcademyIds->count(),
                'trialing_academies' => $trialAcademyIds->count(),
                'lapsed_academies' => max(0, Academy::query()->count() - $withAccess),
            ],
            'payments' => $payments,
            'filters' => ['status' => $status, 'search' => $search],
        ]);
    }
}
