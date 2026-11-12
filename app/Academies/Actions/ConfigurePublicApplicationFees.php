<?php

namespace App\Academies\Actions;

use App\Academies\PublicProgrammePaymentPlans;
use App\Enums\AcademyInvoicePurpose;
use App\Enums\AcademyInvoiceStatus;
use App\Models\Academy;
use App\Models\AcademyCycleFee;
use App\Models\AcademyInvoice;
use App\Models\Application;
use App\Models\OnlinePaymentIntent;
use App\Payments\Contracts\PaymentProvider;
use App\Payments\SettleOnlinePayment;
use App\Platform\AuditLogger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ConfigurePublicApplicationFees
{
    public function __construct(
        private readonly PublicProgrammePaymentPlans $paymentPlans,
        private readonly IssueApplicationInvoice $issueInvoice,
        private readonly AuditLogger $audit,
        private readonly PaymentProvider $provider,
        private readonly SettleOnlinePayment $settle,
    ) {}

    /**
     * @param  list<int>  $cycleFeeIds
     */
    public function handle(
        Academy $academy,
        Application $application,
        ?int $paymentPlanId,
        array $cycleFeeIds,
        Request $request,
    ): Application {
        $this->resolveUnfinishedPayments($application, $request);

        return DB::transaction(function () use ($academy, $application, $paymentPlanId, $cycleFeeIds, $request): Application {
            $application = Application::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();
            abort_unless($application->academy_id === $academy->id, 404);
            $offering = $application->programmeCycle()->with('cycle')->firstOrFail();
            abort_unless($offering->academy_id === $academy->id, 404);

            $availablePlans = $this->paymentPlans->forOffering($offering, $application->cohort_id);
            $selectedPlan = $paymentPlanId === null ? null : $availablePlans->firstWhere('id', $paymentPlanId);
            if ($availablePlans->isNotEmpty() && $selectedPlan === null) {
                throw ValidationException::withMessages([
                    'programme_cycle_payment_plan_id' => 'Select an available payment plan.',
                ]);
            }
            if ($availablePlans->isEmpty() && $paymentPlanId !== null) {
                throw ValidationException::withMessages([
                    'programme_cycle_payment_plan_id' => 'This programme does not have an available payment plan.',
                ]);
            }

            $eligibleFees = $this->eligibleCycleFees($application);
            $requestedFeeIds = collect($cycleFeeIds)->map(fn (mixed $id): int => (int) $id)->unique()->values();
            if ($requestedFeeIds->diff($eligibleFees->modelKeys())->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'cycle_fee_ids' => 'One or more selected fees are not available for this application.',
                ]);
            }
            $selectedFeeIds = $requestedFeeIds
                ->merge($eligibleFees->where('is_compulsory', true)->modelKeys())
                ->unique()
                ->values();
            $activeFeeInvoices = $application->invoices()
                ->whereNotNull('academy_cycle_fee_id')
                ->where('status', '!=', AcademyInvoiceStatus::Void->value)
                ->get()
                ->keyBy('academy_cycle_fee_id');
            $selectionChanged = (int) ($application->programme_cycle_payment_plan_id ?? 0) !== (int) $selectedPlan?->id
                || $selectedFeeIds->sort()->values()->all() !== $activeFeeInvoices->keys()->map(fn (mixed $id): int => (int) $id)->sort()->values()->all();

            if ($selectionChanged && $application->onlinePaymentIntents()->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages([
                    'programme_cycle_payment_plan_id' => 'A payment has already been started. Complete it or contact the academy before changing these fees.',
                ]);
            }

            $activeProgrammeInvoice = $application->invoices()
                ->where('purpose', AcademyInvoicePurpose::Programme->value)
                ->where('status', '!=', AcademyInvoiceStatus::Void->value)
                ->latest('id')
                ->first();
            if ($activeProgrammeInvoice !== null
                && $activeProgrammeInvoice->programme_cycle_payment_plan_id !== $selectedPlan?->id) {
                $this->voidInvoice($academy, $activeProgrammeInvoice, 'Applicant changed the public payment plan.', $request);
                $activeProgrammeInvoice = null;
            }

            $application->programme_cycle_payment_plan_id = $selectedPlan?->id;
            $application->save();
            if ($selectedPlan !== null && $activeProgrammeInvoice === null) {
                $this->issueInvoice->programmeFee($academy, $application, null, $request);
            }

            foreach ($eligibleFees as $fee) {
                $selected = $selectedFeeIds->contains($fee->id);
                $invoice = $application->invoices()->where('academy_cycle_fee_id', $fee->id)->first();
                if ($selected && $invoice === null) {
                    $this->issueCycleFeeInvoice($academy, $application, $fee, $request);
                } elseif ($selected && $invoice->status === AcademyInvoiceStatus::Void) {
                    $this->assertInvoiceCanChange($invoice);
                    $invoice->update([
                        'status' => AcademyInvoiceStatus::Issued,
                        'cancellation_reason' => null,
                        'cancelled_at' => null,
                        'cancelled_by_user_id' => null,
                    ]);
                    $this->audit->record('academy.application_cycle_fee.restored', null, $academy, $invoice, [
                        'application_id' => $application->id,
                        'cycle_fee_id' => $fee->id,
                    ], $request);
                } elseif (! $selected && $invoice !== null && $invoice->status !== AcademyInvoiceStatus::Void) {
                    $this->voidInvoice($academy, $invoice, 'Applicant removed an optional fee before payment.', $request);
                }
            }

            $this->audit->record('academy.application_fee_selection.updated', null, $academy, $application, [
                'programme_cycle_payment_plan_id' => $selectedPlan?->id,
                'academy_cycle_fee_ids' => $selectedFeeIds->all(),
            ], $request);

            return $application->fresh();
        });
    }

    /**
     * Checks earlier Paystack attempts before the fees change. A paid attempt is recorded, which then
     * locks the fees. An attempt the applicant never finished is cancelled so they can choose again.
     * Runs before the database transaction so no rows stay locked during the Paystack call.
     */
    private function resolveUnfinishedPayments(Application $application, Request $request): void
    {
        $pending = $application->onlinePaymentIntents()->where('status', 'pending')->get();
        foreach ($pending as $intent) {
            /** @var OnlinePaymentIntent $intent */
            try {
                $providerData = $this->provider->verify($intent->reference);
                $intent = $this->settle->handle($intent, $providerData, $request);
            } catch (RuntimeException) {
                // Paystack could not be reached; the attempt stays pending and keeps blocking changes.
                continue;
            }
            if ($intent->status === 'pending' && ($providerData['status'] ?? null) === 'abandoned') {
                $intent->update([
                    'status' => 'cancelled',
                    'failure_reason' => 'Not completed; replaced by a new fee selection or payment attempt.',
                ]);
            }
        }
    }

    /** True once a programme or cycle fee invoice has a payment or adjustment, so the applicant can no longer change fees. */
    public function selectionLocked(Application $application): bool
    {
        return $application->invoices()
            ->where('purpose', '!=', AcademyInvoicePurpose::Application->value)
            ->where('status', '!=', AcademyInvoiceStatus::Void->value)
            ->where(fn ($query) => $query->where('paid_minor', '>', 0)
                ->orWhere('written_off_minor', '>', 0)
                ->orWhere('scholarship_concession_minor', '>', 0)
                ->orWhere('sponsor_responsibility_minor', '>', 0)
                ->orWhereHas('allocations')
                ->orWhereNotIn('status', [AcademyInvoiceStatus::Issued->value]))
            ->exists();
    }

    /** @return Collection<int, AcademyCycleFee> */
    public function eligibleCycleFees(Application $application): Collection
    {
        $offering = $application->programmeCycle;
        abort_if($offering === null || $offering->academy_id !== $application->academy_id, 404);

        return AcademyCycleFee::query()
            ->where('academy_id', $application->academy_id)
            ->where('academy_cycle_id', $offering->academy_cycle_id)
            ->where('term_scope_id', 0)
            ->where('is_active', true)
            ->where(function ($query) use ($application): void {
                $query->where('cohort_scope_id', 0);
                if ($application->cohort_id !== null) {
                    $query->orWhere('cohort_scope_id', $application->cohort_id);
                }
            })
            ->where(function ($query) use ($application): void {
                $query->where('applies_to_all_programmes', true)
                    ->orWhereHas('programmes', fn ($query) => $query->whereKey($application->programme_id));
            })
            ->orderByDesc('is_compulsory')
            ->orderBy('name')
            ->get();
    }

    private function issueCycleFeeInvoice(
        Academy $academy,
        Application $application,
        AcademyCycleFee $fee,
        Request $request,
    ): AcademyInvoice {
        do {
            $number = 'INV-'.now()->format('Ym').'-'.Str::upper(Str::random(8));
        } while ($academy->invoices()->where('number', $number)->exists());

        $offering = $application->programmeCycle;
        $invoice = AcademyInvoice::query()->create([
            'academy_id' => $academy->id,
            'number' => $number,
            'application_id' => $application->id,
            'fee_name' => $fee->name,
            'academy_cycle_id' => $fee->academy_cycle_id,
            'academy_cycle_fee_id' => $fee->id,
            'programme_cycle_id' => $offering?->id,
            'programme_id' => $application->programme_id,
            'branch_id' => $application->branch_id,
            'purpose' => AcademyInvoicePurpose::Custom,
            'status' => AcademyInvoiceStatus::Issued,
            'currency' => $fee->currency,
            'total_minor' => $fee->amount_minor,
            'paid_minor' => 0,
            'written_off_minor' => 0,
            'issued_at' => now(),
            'due_on' => $fee->term()->value('ends_on') ?? $offering?->cycle?->ends_on,
            'created_by_user_id' => null,
        ]);
        $invoice->items()->create([
            'academy_id' => $academy->id,
            'description' => $fee->name,
            'amount_minor' => $fee->amount_minor,
            'sort_order' => 0,
        ]);
        $invoice->installments()->create([
            'academy_id' => $academy->id,
            'name' => $fee->name,
            'amount_minor' => $fee->amount_minor,
            'paid_minor' => 0,
            'written_off_minor' => 0,
            'due_on' => $invoice->due_on,
            'due_offset_days' => null,
            'sequence' => 1,
        ]);
        $this->audit->record('academy.application_cycle_fee.issued', null, $academy, $invoice, [
            'application_id' => $application->id,
            'cycle_fee_id' => $fee->id,
        ], $request);

        return $invoice;
    }

    private function voidInvoice(Academy $academy, AcademyInvoice $invoice, string $reason, Request $request): void
    {
        $this->assertInvoiceCanChange($invoice);
        $invoice->update([
            'status' => AcademyInvoiceStatus::Void,
            'cancellation_reason' => $reason,
            'cancelled_at' => now(),
            'cancelled_by_user_id' => null,
        ]);
        $this->audit->record('academy.invoice.cancelled', null, $academy, $invoice, [
            'reason' => $reason,
            'source' => 'public_application_fee_selection',
        ], $request);
    }

    private function assertInvoiceCanChange(AcademyInvoice $invoice): void
    {
        if ($invoice->paid_minor !== 0
            || $invoice->written_off_minor !== 0
            || $invoice->scholarship_concession_minor !== 0
            || $invoice->sponsor_responsibility_minor !== 0
            || $invoice->allocations()->exists()
            || ! in_array($invoice->status, [AcademyInvoiceStatus::Issued, AcademyInvoiceStatus::Void], true)) {
            throw ValidationException::withMessages([
                'programme_cycle_payment_plan_id' => 'Fees cannot be changed after a payment or financial adjustment has been recorded.',
            ]);
        }
    }
}
