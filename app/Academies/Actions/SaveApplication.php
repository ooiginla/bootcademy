<?php

namespace App\Academies\Actions;

use App\Enums\ApplicationStatus;
use App\Models\Academy;
use App\Models\AcademyApiKey;
use App\Models\Application;
use App\Models\ProgrammeCyclePaymentPlan;
use App\Models\User;
use App\Platform\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class SaveApplication
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly IssueApplicationInvoice $issueInvoice,
    ) {}

    /** @param array<string, mixed> $data */
    public function handle(
        Academy $academy,
        ?User $actor,
        array $data,
        Request $request,
        ?Application $application = null,
        bool $issueProgrammeInvoice = false,
    ): Application {
        abort_if($application !== null && $application->academy_id !== $academy->id, 404);
        if (! $academy->collect_guardian_information) {
            $data = Arr::except($data, [
                'guardian_first_name', 'guardian_last_name', 'guardian_email', 'guardian_phone',
                'guardian_whatsapp_number', 'guardian_relationship', 'guardian_signature', 'guardian_consent_at',
            ]);
        }
        $creating = $application === null;
        $reference = $application?->reference;
        if ($creating) {
            do {
                $reference = 'APP-'.Str::upper(Str::random(10));
            } while ($academy->applications()->where('reference', $reference)->exists());
        }

        $signature = Arr::pull($data, 'guardian_signature');
        $signaturePath = null;
        $oldSignaturePath = $application?->guardian_signature_path;

        if (($data['cohort_id'] ?? null) !== null) {
            $cohortId = (int) $data['cohort_id'];
            $cohort = $academy->cohorts()->with('programmeCycle')->findOrFail($cohortId);
            $cohortBranchId = $cohort->branch_id ?? $cohort->programmeCycle->branch_id;
            $effectiveBranchId = $cohortBranchId ?? ($data['branch_id'] ?? null);
            if ($application !== null && $application->invoices()->exists()
                && ($cohort->programme_cycle_id !== $application->programme_cycle_id
                    || $effectiveBranchId !== $application->branch_id)) {
                throw ValidationException::withMessages([
                    'cohort_id' => 'Choose a cohort with the same programme offering and branch because this application has invoices.',
                ]);
            }
            $data['programme_cycle_id'] = $cohort->programme_cycle_id;
            $data['programme_id'] = $cohort->programmeCycle->programme_id;
            $data['branch_id'] = $effectiveBranchId;
        }

        if (($data['programme_cycle_id'] ?? null) !== null) {
            $offeringQuery = $academy->programmeCycles()->whereKey($data['programme_cycle_id']);
            if ($application === null || (int) $data['programme_cycle_id'] !== $application->programme_cycle_id) {
                $offeringQuery->where('is_active', true)->whereHas('cycle', fn ($query) => $query->where('status', 'active'));
            }
            $offering = $offeringQuery->firstOrFail();
            $branchId = ($data['branch_id'] ?? null) === null ? null : (int) $data['branch_id'];
            if ($offering->branch_id !== null && $offering->branch_id !== $branchId) {
                throw ValidationException::withMessages([
                    'programme_cycle_id' => 'The selected programme fees are not available for this branch.',
                ]);
            }
            if ($offering->branch_id === null && $branchId !== null
                && ($data['cohort_id'] ?? null) === null
                && ! ($application?->invoices()->exists() ?? false)) {
                $offering = $academy->programmeCycles()
                    ->where('academy_cycle_id', $offering->academy_cycle_id)
                    ->where('programme_id', $offering->programme_id)
                    ->where('branch_id', $branchId)
                    ->where('is_active', true)
                    ->first() ?? $offering;
                $data['programme_cycle_id'] = $offering->id;
            }
            $data['programme_id'] = $offering->programme_id;

            if (($data['programme_cycle_payment_plan_id'] ?? null) !== null) {
                $paymentPlanId = (int) $data['programme_cycle_payment_plan_id'];
                $paymentPlan = ProgrammeCyclePaymentPlan::query()
                    ->where('academy_id', $academy->id)
                    ->where('programme_cycle_id', $offering->id)
                    ->where('term_scope_id', 0)
                    ->when($creating, fn ($query) => $query->where('is_active', true))
                    ->find($paymentPlanId);
                $cohortScopeId = (int) ($data['cohort_id'] ?? 0);
                if ($paymentPlan === null || ! in_array($paymentPlan->cohort_scope_id, [0, $cohortScopeId], true)) {
                    throw ValidationException::withMessages([
                        'programme_cycle_payment_plan_id' => 'The selected payment plan is not available for this application.',
                    ]);
                }
                if ($application !== null && $application->invoices()->exists()
                    && $application->programme_cycle_payment_plan_id !== $paymentPlan->id) {
                    throw ValidationException::withMessages([
                        'programme_cycle_payment_plan_id' => 'The payment plan cannot change after an invoice has been issued.',
                    ]);
                }
            }
        }

        try {
            if ($signature instanceof UploadedFile) {
                $storedPath = $signature->store("academies/{$academy->id}/applications/signatures", 'local');
                throw_if($storedPath === false, RuntimeException::class, 'The guardian signature could not be stored.');
                $signaturePath = $storedPath;
                $data['guardian_signature_path'] = $storedPath;
            }

            $application = DB::transaction(function () use ($academy, $actor, $data, $reference, $request, $application, $creating, $issueProgrammeInvoice): Application {
                if ($creating) {
                    $application = Application::query()->create([
                        ...$data,
                        'academy_id' => $academy->id,
                        'reference' => $reference,
                        'source' => $data['source'] ?? 'admin',
                        'status' => ApplicationStatus::Submitted,
                    ]);
                } else {
                    $application->fill($data);
                    $application->save();
                }
                $this->issueInvoice->applicationFee($academy, $application, $actor, $request);
                if ($issueProgrammeInvoice) {
                    $this->issueInvoice->programmeFee($academy, $application, $actor, $request);
                }

                return $application;
            });
        } catch (Throwable $exception) {
            if ($signaturePath !== null) {
                Storage::disk('local')->delete($signaturePath);
            }

            throw $exception;
        }
        if ($signaturePath !== null && $oldSignaturePath !== null) {
            Storage::disk('local')->delete($oldSignaturePath);
        }
        $apiKey = $request->attributes->get('academy_api_key');
        $this->audit->record($creating ? 'academy.application.created' : 'academy.application.updated', $actor, $academy, $application, [
            'reference' => $reference,
            'programme_id' => $application->programme_id,
            'programme_cycle_payment_plan_id' => $application->programme_cycle_payment_plan_id,
            'branch_id' => $application->branch_id,
            'source' => $application->source,
            'api_key_id' => $apiKey instanceof AcademyApiKey ? $apiKey->id : null,
        ], $request);

        return $application;
    }
}
