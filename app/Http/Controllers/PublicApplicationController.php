<?php

namespace App\Http\Controllers;

use App\Academies\Actions\ConfigurePublicApplicationFees;
use App\Academies\Actions\SaveApplication;
use App\Academies\ApplicationIntakeAvailability;
use App\Academies\PublicProgrammePaymentPlans;
use App\Enums\AcademyInvoicePurpose;
use App\Http\Requests\Public\StorePublicApplicationRequest;
use App\Models\AcademyInvoice;
use App\Models\AcademyInvoiceItem;
use App\Models\AcademyPayment;
use App\Models\AcademyWebsite;
use App\Models\Application;
use App\Models\Branch;
use App\Models\OnlinePaymentIntent;
use App\Models\ProgrammeCycleFeeItem;
use App\Notifications\ApplicationSubmittedNotification;
use App\Payments\ApplicationPaymentPlan;
use App\Payments\Contracts\PaymentProvider;
use App\Payments\PaystackFeeCalculator;
use App\Payments\SettleOnlinePayment;
use App\Support\AccessibleColor;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class PublicApplicationController extends Controller
{
    public function store(
        StorePublicApplicationRequest $request,
        string $subdomain,
        SaveApplication $save,
        ConfigurePublicApplicationFees $configureFees,
        ApplicationIntakeAvailability $availability,
    ): RedirectResponse {
        $academy = $request->academyOrFail();
        $data = $request->validated();
        $intake = $availability->query($academy)->with('programmeCycle')->whereKey($data['application_intake_id'])->firstOrFail();
        $offering = $intake->programmeCycle;
        $duplicate = $academy->applications()->where('first_name', $data['first_name'])->where('last_name', $data['last_name'])
            ->where('email', $data['email'])->where('status', '!=', 'rejected')->first();
        if ($duplicate !== null) {
            throw ValidationException::withMessages(['email' => 'A matching active application already exists. Contact the academy if you need help.']);
        }
        $token = Str::random(64);
        $data = Arr::except($data, ['consent_accepted', 'recaptcha_token']);
        $data['programme_id'] = $offering->programme_id;
        $data['programme_cycle_id'] = $offering->id;
        $data['cohort_id'] = $intake->cohort_id;
        $data['branch_id'] = $intake->branch_id ?? $data['preferred_branch_id'] ?? $offering->branch_id;
        unset($data['preferred_branch_id']);
        $data['source'] = 'website';
        $data['public_access_token_hash'] = hash('sha256', $token);
        $data['guardian_consent_at'] = $academy->collect_guardian_information ? now() : null;
        $data['privacy_policy_version'] = 'public-application-v1';
        $application = DB::transaction(function () use ($academy, $data, $request, $save, $configureFees): Application {
            $application = $save->handle($academy, null, $data, $request);

            return $configureFees->handle(
                $academy,
                $application,
                isset($data['programme_cycle_payment_plan_id']) ? (int) $data['programme_cycle_payment_plan_id'] : null,
                [],
                $request,
            );
        });
        Notification::route('mail', $application->email)
            ->notify(new ApplicationSubmittedNotification($application));

        $route = $request->routeIs('public.tenant.*') ? 'public.tenant.application-fees' : 'public.academy.application-fees';

        return redirect()->route($route, [
            'subdomain' => $subdomain,
            'reference' => $application->reference,
            'token' => $token,
            'submitted' => 1,
        ]);
    }

    public function fees(
        Request $request,
        string $subdomain,
        string $reference,
        ApplicationPaymentPlan $plans,
        PublicProgrammePaymentPlans $paymentPlans,
        ConfigurePublicApplicationFees $feeSelection,
    ): Response {
        [$website, $application] = $this->authorizedApplication($request, $subdomain, $reference);
        $application->load(['programmeCycle.programme', 'programmeCycle.cycle', 'programmeCycle.branch', 'programmeCycle.feeItems', 'invoices.items']);
        $offering = $application->programmeCycle;
        $programmeInvoice = $application->invoices->first(
            fn (AcademyInvoice $invoice): bool => $invoice->purpose === AcademyInvoicePurpose::Programme
                && $invoice->status->value !== 'void',
        );
        $applicableFeeItems = $programmeInvoice !== null
            ? $programmeInvoice->items->sortBy('sort_order')->values()->map(fn (AcademyInvoiceItem $item): array => [
                'name' => $item->description, 'amount_minor' => (int) $item->amount_minor,
            ])
            : $offering->feeItems
                ->filter(fn (ProgrammeCycleFeeItem $item): bool => $item->cohort_id === null || $item->cohort_id === $application->cohort_id)
                ->sortBy('sort_order')->values()->map(fn (ProgrammeCycleFeeItem $item): array => [
                    'name' => $item->name, 'amount_minor' => (int) $item->amount_minor,
                ]);
        $branch = $offering->getRelation('branch');
        $baseUrl = $request->routeIs('public.tenant.*') ? '' : '/sites/'.$website->subdomain;
        $availablePlans = $paymentPlans->forOffering($offering, $application->cohort_id);
        $eligibleCycleFees = $feeSelection->eligibleCycleFees($application);
        $selectedCycleFeeIds = $application->invoices()
            ->whereNotNull('academy_cycle_fee_id')
            ->where('status', '!=', 'void')
            ->pluck('academy_cycle_fee_id');

        return Inertia::render('public/application-fees', $this->common($website, $baseUrl) + [
            'submitted' => $request->boolean('submitted'),
            'application' => [
                'reference' => $application->reference,
                'name' => trim($application->first_name.' '.$application->middle_name.' '.$application->last_name),
                'email' => $application->email,
                'token' => (string) $request->query('token'),
            ],
            'offering' => [
                'programme_name' => $offering->programme->name,
                'cycle_name' => $offering->cycle->name,
                'branch_name' => $branch instanceof Branch ? $branch->name : 'All locations',
                'currency' => $offering->currency,
                'fee_name' => $offering->fee_name,
                'fee_items' => $applicableFeeItems,
            ],
            'payment_plans' => $availablePlans->map(fn ($plan): array => [
                'id' => $plan->id,
                'name' => $plan->name,
                'description' => $plan->description,
                'is_selected' => $application->programme_cycle_payment_plan_id === $plan->id,
                'items' => $plan->items->sortBy('sort_order')->values()->map(fn ($item): array => [
                    'name' => $item->name,
                    'description' => $item->description,
                    'amount' => Money::fromMinor($item->amount_minor),
                ]),
                'first_payment' => $plan->installments->sortBy('sequence')->values()->map(fn ($installment): array => [
                    'name' => $installment->name,
                    'amount' => Money::fromMinor($installment->amount_minor),
                    'due_on' => $installment->due_on?->toDateString(),
                    'due_offset_days' => $installment->due_offset_days,
                ])->first(),
                'payment_count' => $plan->installments->count(),
            ])->values(),
            'cycle_fees' => $eligibleCycleFees->map(fn ($fee): array => [
                'id' => $fee->id,
                'name' => $fee->name,
                'category' => $fee->category,
                'amount' => Money::fromMinor($fee->amount_minor),
                'is_compulsory' => $fee->is_compulsory,
                'is_selected' => $fee->is_compulsory || $selectedCycleFeeIds->contains($fee->id),
            ])->values(),
            'payment_options' => $plans->options($website->academy, $application),
            'paystack_ready' => filled(config('services.paystack.secret_key')),
            'completion_url' => $this->completionUrl($request, $subdomain, $application, (string) $request->query('token')),
        ]);
    }

    public function configureFees(
        Request $request,
        string $subdomain,
        string $reference,
        ConfigurePublicApplicationFees $configure,
    ): RedirectResponse {
        [$website, $application] = $this->authorizedApplication($request, $subdomain, $reference);
        $validated = $request->validate([
            'programme_cycle_payment_plan_id' => ['nullable', 'integer'],
            'cycle_fee_ids' => ['array'],
            'cycle_fee_ids.*' => ['integer', 'distinct'],
        ]);
        $configure->handle(
            $website->academy,
            $application,
            isset($validated['programme_cycle_payment_plan_id'])
                ? (int) $validated['programme_cycle_payment_plan_id']
                : null,
            array_values(array_map('intval', $validated['cycle_fee_ids'] ?? [])),
            $request,
        );
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Your fee selection has been updated.']);
        $route = $request->routeIs('public.tenant.*') ? 'public.tenant.application-fees' : 'public.academy.application-fees';

        return redirect()->route($route, [
            'subdomain' => $subdomain,
            'reference' => $application->reference,
            'token' => (string) $request->input('token'),
        ]);
    }

    public function complete(Request $request, string $subdomain, string $reference): Response
    {
        [$website, $application] = $this->authorizedApplication($request, $subdomain, $reference);
        $intent = null;
        if ($request->filled('intent')) {
            $intent = $application->onlinePaymentIntents()
                ->where('academy_id', $application->academy_id)
                ->whereKey($request->integer('intent'))
                ->with('payment')
                ->firstOrFail();
        }
        $baseUrl = $request->routeIs('public.tenant.*') ? '' : '/sites/'.$website->subdomain;
        $payment = $intent?->getRelation('payment');

        return Inertia::render('public/application-complete', $this->common($website, $baseUrl) + [
            'application' => [
                'reference' => $application->reference,
                'name' => trim($application->first_name.' '.$application->middle_name.' '.$application->last_name),
                'email' => $application->email,
            ],
            'payment' => $intent === null ? [
                'status' => 'skipped', 'amount' => null, 'currency' => null, 'reference' => null,
            ] : [
                'status' => $intent->status,
                'amount' => Money::fromMinor($intent->charged_amount_minor),
                'currency' => $intent->currency,
                'reference' => $payment instanceof AcademyPayment ? $payment->reference : $intent->reference,
            ],
        ]);
    }

    public function initialize(
        Request $request,
        string $subdomain,
        string $reference,
        ApplicationPaymentPlan $plans,
        PaymentProvider $provider,
        PaystackFeeCalculator $feeCalculator,
    ): JsonResponse {
        [, $application] = $this->authorizedApplication($request, $subdomain, $reference);
        $validated = $request->validate(['selection' => ['required', 'string', 'max:80']]);
        $allocations = $plans->allocations($application->academy, $application, $validated['selection']);
        $base = (int) collect($allocations)->sum('amount_minor');
        abort_if($base <= 0, 422, 'There is no outstanding amount for this option.');
        $surcharge = $feeCalculator->surcharge($application->academy, $base);
        $charged = $base + $surcharge;
        $existing = $application->onlinePaymentIntents()->where('selection_key', $validated['selection'])
            ->where('status', 'pending')->where('base_amount_minor', $base)->where('charged_amount_minor', $charged)
            ->where('created_at', '>=', now()->subMinutes(30))->whereNotNull('access_code')->latest()->first();
        if ($existing !== null) {
            return $this->intentResponse($request, $subdomain, $application, $existing);
        }

        $referenceValue = 'BCM-'.$application->academy_id.'-'.Str::upper(Str::random(20));
        $intent = OnlinePaymentIntent::query()->create([
            'academy_id' => $application->academy_id, 'application_id' => $application->id,
            'provider' => 'paystack', 'reference' => $referenceValue, 'selection_key' => $validated['selection'],
            'status' => 'pending', 'currency' => $application->programmeCycle->currency,
            'base_amount_minor' => $base, 'surcharge_minor' => $surcharge,
            'charged_amount_minor' => $charged, 'allocation_plan' => $allocations,
        ]);
        try {
            $initialized = $provider->initialize((string) $application->email, $charged, $intent->currency, $intent->reference, [
                'academy_id' => $application->academy_id, 'application_reference' => $application->reference,
                'payment_intent_id' => $intent->id,
            ]);
            $intent->update(['access_code' => $initialized['access_code'], 'initialized_at' => now()]);
        } catch (RuntimeException $exception) {
            $intent->update(['status' => 'failed', 'failure_reason' => $exception->getMessage()]);

            return response()->json([
                'message' => $exception->getMessage(),
                'completion_url' => $this->completionUrl(
                    $request,
                    $subdomain,
                    $application,
                    (string) $request->input('token'),
                    $intent,
                ),
            ], 502);
        }

        return $this->intentResponse($request, $subdomain, $application, $intent->fresh());
    }

    public function verify(
        Request $request,
        string $subdomain,
        string $reference,
        OnlinePaymentIntent $intent,
        PaymentProvider $provider,
        SettleOnlinePayment $settle,
    ): JsonResponse {
        [, $application] = $this->authorizedApplication($request, $subdomain, $reference);
        abort_unless($intent->academy_id === $application->academy_id && $intent->application_id === $application->id, 404);
        if (! in_array($intent->status, ['verified', 'review_required'], true)) {
            try {
                $intent = $settle->handle($intent, $provider->verify($intent->reference), $request);
            } catch (RuntimeException $exception) {
                return response()->json(['status' => 'pending', 'message' => $exception->getMessage()], 202);
            }
        }

        return response()->json([
            'status' => $intent->status,
            'payment_reference' => $intent->payment?->reference,
            'message' => $intent->status === 'verified' ? 'Payment verified. Your receipt has been emailed.' : $intent->failure_reason,
            'completion_url' => $this->completionUrl($request, $subdomain, $application, (string) $request->input('token'), $intent),
        ], $intent->status === 'verified' ? 200 : 202);
    }

    /** @return array{AcademyWebsite, Application} */
    private function authorizedApplication(Request $request, string $subdomain, string $reference): array
    {
        $website = AcademyWebsite::query()->where('subdomain', $subdomain)->where('is_published', true)->with('academy')->firstOrFail();
        $application = $website->academy->applications()->where('reference', $reference)->firstOrFail();
        $token = (string) ($request->query('token') ?: $request->input('token'));
        abort_unless($token !== '' && is_string($application->public_access_token_hash)
            && hash_equals($application->public_access_token_hash, hash('sha256', $token)), 404);

        return [$website, $application];
    }

    private function intentResponse(Request $request, string $subdomain, Application $application, OnlinePaymentIntent $intent): JsonResponse
    {
        $route = $request->routeIs('public.tenant.*') ? 'public.tenant.payment-verify' : 'public.academy.payment-verify';

        return response()->json([
            'access_code' => $intent->access_code, 'reference' => $intent->reference,
            'verify_url' => route($route, ['subdomain' => $subdomain, 'reference' => $application->reference, 'intent' => $intent->id]),
            'completion_url' => $this->completionUrl($request, $subdomain, $application, (string) $request->input('token'), $intent),
        ]);
    }

    private function completionUrl(
        Request $request,
        string $subdomain,
        Application $application,
        string $token,
        ?OnlinePaymentIntent $intent = null,
    ): string {
        $route = $request->routeIs('public.tenant.*') ? 'public.tenant.application-complete' : 'public.academy.application-complete';

        return route($route, array_filter([
            'subdomain' => $subdomain,
            'reference' => $application->reference,
            'token' => $token,
            'intent' => $intent?->id,
        ], fn ($value): bool => $value !== null && $value !== ''));
    }

    /** @return array<string, mixed> */
    private function common(AcademyWebsite $website, string $baseUrl): array
    {
        $academy = $website->academy;

        return [
            'academy' => [
                'name' => $academy->name, 'type' => $academy->academy_type,
                'logo_url' => $academy->logo_path ? $baseUrl.'/logo' : null,
                'member_singular' => $academy->member_label_singular, 'member_plural' => $academy->member_label_plural,
                'currency' => $academy->currency, 'theme' => AccessibleColor::theme($academy->primary_color, $academy->secondary_color),
            ],
            'site' => ['subdomain' => $website->subdomain, 'theme_key' => $website->theme_key, 'sections' => $website->resolvedSections()],
            'base_url' => $baseUrl,
        ];
    }
}
