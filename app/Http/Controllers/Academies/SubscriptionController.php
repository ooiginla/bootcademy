<?php

namespace App\Http\Controllers\Academies;

use App\Academies\AcademyContext;
use App\Enums\BillingInterval;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\SubscriptionPayment;
use App\Payments\Contracts\PaymentProvider;
use App\Platform\AcademyEntitlements;
use App\Platform\Subscriptions\InitializeSubscriptionPayment;
use App\Platform\Subscriptions\PlanLimits;
use App\Platform\Subscriptions\SettleSubscriptionPayment;
use App\Platform\Subscriptions\SubscriptionPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class SubscriptionController extends Controller
{
    public function show(AcademyContext $context, PlanLimits $limits, SubscriptionPresenter $presenter): Response
    {
        $academy = $context->academy();
        $entitlements = AcademyEntitlements::for($academy);
        $canPay = $context->canManageAcademy();

        return Inertia::render('academies/subscription', [
            'state' => $entitlements->state(),
            'current' => $presenter->period($entitlements->currentPeriod()),
            'access_ends_at' => $entitlements->accessEndsAt()?->toIso8601String(),
            'upcoming' => $entitlements->upcomingPeriods()->map(fn ($period) => $presenter->period($period))->values(),
            'plan_name' => $entitlements->plan()?->name,
            'next_period_starts_at' => $canPay ? $presenter->nextPeriodStart($entitlements)->toIso8601String() : null,
            'can_pay' => $canPay,
            'online_payment_available' => filled(config('services.paystack.secret_key')),
            'plans' => $canPay ? $presenter->plans($academy, $entitlements->plan()?->id) : [],
            'comparison' => $presenter->comparison(),
            'usage' => $canPay ? $limits->usage($academy) : null,
            'limits' => $limits->limits($academy),
            'payments' => $canPay
                ? $academy->subscriptionPayments()->with(['plan', 'period'])->latest('id')->limit(10)->get()
                    ->map(fn (SubscriptionPayment $payment): array => $presenter->payment($payment))->values()
                : [],
        ]);
    }

    public function initialize(
        Request $request,
        AcademyContext $context,
        InitializeSubscriptionPayment $initialize,
    ): JsonResponse {
        abort_unless($context->canManageAcademy(), 403);
        $validated = $request->validate([
            'plan_id' => ['required', 'integer', Rule::exists('plans', 'id')->where('is_active', true)],
            'billing_interval' => ['required', Rule::enum(BillingInterval::class)],
        ]);
        $plan = Plan::query()->findOrFail((int) $validated['plan_id']);
        $payment = $initialize->handle(
            $context->academy(),
            $plan,
            BillingInterval::from((string) $validated['billing_interval']),
            $request->user(),
            $request,
        );

        return response()->json([
            'access_code' => $payment->access_code,
            'reference' => $payment->reference,
            'verify_url' => route('academy.subscription.payments.verify', $payment),
        ]);
    }

    public function verify(
        Request $request,
        SubscriptionPayment $subscriptionPayment,
        AcademyContext $context,
        PaymentProvider $provider,
        SettleSubscriptionPayment $settle,
    ): JsonResponse {
        abort_unless($subscriptionPayment->academy_id === $context->id(), 404);
        abort_unless($context->canManageAcademy(), 403);
        if ($subscriptionPayment->status !== 'pending') {
            return response()->json(['status' => $subscriptionPayment->status]);
        }
        try {
            $settled = $settle->handle($subscriptionPayment, $provider->verify($subscriptionPayment->reference), $request);
        } catch (RuntimeException $exception) {
            return response()->json(['status' => $subscriptionPayment->refresh()->status, 'message' => $exception->getMessage()], 422);
        }
        $settled->forceFill(['last_checked_at' => now()])->save();

        return response()->json(['status' => $settled->status]);
    }
}
