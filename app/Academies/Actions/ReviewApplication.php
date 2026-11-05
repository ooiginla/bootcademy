<?php

namespace App\Academies\Actions;

use App\Enums\AcademyCycleType;
use App\Enums\AcademyInvoiceStatus;
use App\Enums\ApplicationStatus;
use App\Models\Academy;
use App\Models\AcademyInvoice;
use App\Models\Application;
use App\Models\Player;
use App\Models\User;
use App\Platform\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class ReviewApplication
{
    public function __construct(
        private readonly SavePlayer $savePlayer,
        private readonly SaveEnrollment $saveEnrollment,
        private readonly IssueApplicationInvoice $issueInvoice,
        private readonly IssuePlayerCycleFeeInvoice $issueCycleFeeInvoice,
        private readonly AuditLogger $audit,
    ) {}

    /** @param array<string, mixed> $data */
    public function handle(Academy $academy, Application $application, User $actor, array $data, Request $request): Application
    {
        return DB::transaction(function () use ($academy, $application, $actor, $data, $request): Application {
            $application = Application::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();
            abort_unless($application->academy_id === $academy->id, 404);
            $decision = (string) $data['decision'];
            $from = $application->status;
            $to = $this->nextStatus($from, $decision);

            if ($decision === 'accept') {
                $applicationFeeInvoice = $application->invoices()->where('purpose', 'application')->first();
                if ($applicationFeeInvoice !== null && $applicationFeeInvoice->status !== AcademyInvoiceStatus::Paid) {
                    throw ValidationException::withMessages(['decision' => 'The application fee must be fully paid before this application can be accepted.']);
                }
                $programmeInvoice = $application->programme_cycle_id === null
                    ? null
                    : $this->issueInvoice->programmeFee($academy, $application, $actor, $request);
                $this->enrol($academy, $application, $actor, $data, $request);
                $this->activateRollingInvoiceSchedule($programmeInvoice, $data['activation_on'] ?? today()->toDateString());
                $to = ApplicationStatus::Enrolled;
            }

            if ($decision === 'enrol') {
                $applicationFeeInvoice = $application->invoices()->where('purpose', 'application')->first();
                if ($applicationFeeInvoice !== null && $applicationFeeInvoice->status !== AcademyInvoiceStatus::Paid) {
                    throw ValidationException::withMessages(['decision' => 'The application fee must be fully paid before this application can be enrolled.']);
                }
                $programmeInvoice = $application->programme_cycle_id === null
                    ? null
                    : $this->issueInvoice->programmeFee($academy, $application, $actor, $request);
                $this->enrol($academy, $application, $actor, $data, $request);
                $this->activateRollingInvoiceSchedule($programmeInvoice, $data['activation_on'] ?? today()->toDateString());
                $to = ApplicationStatus::Enrolled;
            }

            $application->fill([
                'status' => $to,
                'review_notes' => $data['review_notes'] ?? null,
                'programme_id' => $data['programme_id'] ?? $application->programme_id,
                'branch_id' => $data['branch_id'] ?? $application->branch_id,
                'reviewed_by_user_id' => $actor->id,
                'reviewed_at' => now(),
                'enrolled_at' => $to === ApplicationStatus::Enrolled ? now() : $application->enrolled_at,
            ]);
            $application->save();
            if ($application->converted_player_id !== null) {
                $academy->invoices()->where('application_id', $application->id)
                    ->update(['player_id' => $application->converted_player_id, 'updated_at' => now()]);
                $academy->payments()->where('application_id', $application->id)
                    ->update(['player_id' => $application->converted_player_id, 'updated_at' => now()]);
                if ($application->programme_cycle_id !== null) {
                    $player = Player::query()->findOrFail($application->converted_player_id);
                    $fees = $academy->cycleFees()->where('academy_cycle_id', $application->programmeCycle->academy_cycle_id)
                        ->where('is_compulsory', true)->where('is_active', true)
                        ->where(function ($query) use ($application): void {
                            $query->where('applies_to_all_programmes', true)
                                ->orWhereHas('programmes', fn ($query) => $query->whereKey($application->programme_id));
                        })->get();
                    foreach ($fees as $fee) {
                        $this->issueCycleFeeInvoice->handle($academy, $player, $fee, $actor, $request, $application);
                    }
                }
            }
            $this->audit->record('academy.application.status_changed', $actor, $academy, $application, [
                'from' => $from->value, 'to' => $to->value, 'decision' => $decision,
            ], $request);

            return $application;
        });
    }

    private function activateRollingInvoiceSchedule(?AcademyInvoice $invoice, mixed $activationOn): void
    {
        if ($invoice === null || $invoice->cycle->type !== AcademyCycleType::Rolling) {
            return;
        }
        $activation = Carbon::parse((string) $activationOn)->startOfDay();
        foreach ($invoice->installments()->whereNull('due_on')->get() as $installment) {
            $installment->due_on = $activation->copy()->addDays((int) ($installment->due_offset_days ?? 0));
            $installment->save();
        }
        $invoice->due_on = $invoice->installments()->min('due_on');
        $invoice->save();
    }

    private function nextStatus(ApplicationStatus $from, string $decision): ApplicationStatus
    {
        $allowed = match ($from) {
            ApplicationStatus::Submitted => ['review', 'accept', 'reject'],
            ApplicationStatus::UnderReview => ['accept', 'reject'],
            ApplicationStatus::Accepted => ['enrol', 'reject'],
            ApplicationStatus::Rejected, ApplicationStatus::Enrolled => [],
        };
        if (! in_array($decision, $allowed, true)) {
            throw ValidationException::withMessages(['decision' => 'That status change is not allowed for this application.']);
        }

        return match ($decision) {
            'review' => ApplicationStatus::UnderReview,
            'accept' => ApplicationStatus::Accepted,
            'reject' => ApplicationStatus::Rejected,
            'enrol' => ApplicationStatus::Enrolled,
        };
    }

    /** @param array<string, mixed> $data */
    private function enrol(Academy $academy, Application $application, User $actor, array $data, Request $request): void
    {
        $offering = $application->programme_cycle_id !== null
            ? $application->programmeCycle()->with('cycle')->firstOrFail()
            : null;
        $programmeId = $offering !== null ? $offering->programme_id : (int) ($data['programme_id'] ?? $application->programme_id);
        $duplicate = Player::query()->where('academy_id', $academy->id)
            ->where('first_name', $application->first_name)->where('last_name', $application->last_name);
        if ($application->date_of_birth !== null) {
            $duplicate->whereDate('date_of_birth', $application->date_of_birth);
        } elseif ($application->guardian_phone !== null) {
            $duplicate->whereHas('guardians', fn ($query) => $query->where('phone', $application->guardian_phone));
        } else {
            $duplicate->whereRaw('1 = 0');
        }
        if ($duplicate->exists()) {
            throw ValidationException::withMessages(['decision' => 'A matching profile already exists. Review that profile before converting this application.']);
        }

        $guardianSignaturePath = $this->copyGuardianSignature($academy, $application);
        $newGuardian = $application->guardian_first_name ? [
            'first_name' => $application->guardian_first_name,
            'last_name' => $application->guardian_last_name,
            'email' => $application->guardian_email,
            'phone' => $application->guardian_phone,
            'whatsapp_number' => $application->guardian_whatsapp_number,
            'signature_path' => $guardianSignaturePath,
            'relationship' => $application->guardian_relationship ?? 'Parent',
        ] : [];

        try {
            $player = $this->savePlayer->handle($academy, $actor, [
                'internal_identifier' => $application->internal_identifier,
                'first_name' => $application->first_name, 'middle_name' => $application->middle_name,
                'last_name' => $application->last_name, 'date_of_birth' => $application->date_of_birth?->toDateString(),
                'gender' => $application->gender, 'phone' => $application->phone,
                'whatsapp_number' => $application->whatsapp_number, 'home_address' => $application->home_address,
                'city' => $application->city, 'state_region' => $application->state_region,
                'country' => $application->country, 'status' => 'active',
                'guardian_ids' => [], 'guardian_relationships' => [],
                'new_guardian' => $newGuardian, 'custom_fields' => $application->custom_fields ?? [],
            ], $request);
            $activation = Carbon::parse($data['activation_on'] ?? today()->toDateString())->startOfDay();
            $startsOn = $activation;
            $endsOn = null;
            if ($offering !== null) {
                if ($offering->cycle->type === AcademyCycleType::Fixed) {
                    if ($activation->gt($offering->cycle->ends_on)) {
                        throw ValidationException::withMessages(['decision' => 'This fixed cycle has already ended and cannot receive a new enrollment.']);
                    }
                    $startsOn = $activation->lt($offering->cycle->starts_on) ? $offering->cycle->starts_on : $activation;
                    $endsOn = $offering->cycle->ends_on;
                } else {
                    $endsOn = $offering->cycle->rollingEndDate($activation);
                }
            }
            $this->saveEnrollment->handle($academy, $player, $actor, [
                'programme_id' => $programmeId,
                'academy_cycle_id' => $offering?->academy_cycle_id,
                'programme_cycle_id' => $offering?->id,
                'branch_id' => $data['branch_id'] ?? $application->branch_id,
                'status' => 'active', 'starts_on' => $startsOn->toDateString(), 'ends_on' => $endsOn?->toDateString(),
            ], $request);
            $application->converted_player_id = $player->id;
        } catch (Throwable $exception) {
            if ($guardianSignaturePath !== null) {
                Storage::disk('local')->delete($guardianSignaturePath);
            }

            throw $exception;
        }
    }

    private function copyGuardianSignature(Academy $academy, Application $application): ?string
    {
        if ($application->guardian_signature_path === null || $application->guardian_first_name === null) {
            return null;
        }

        $disk = Storage::disk('local');
        if (! $disk->exists($application->guardian_signature_path)) {
            throw ValidationException::withMessages([
                'decision' => 'The guardian signature file is missing. Upload it again before enrolling this application.',
            ]);
        }

        $extension = pathinfo($application->guardian_signature_path, PATHINFO_EXTENSION);
        $target = "academies/{$academy->id}/guardians/signatures/".Str::uuid().($extension === '' ? '' : ".{$extension}");
        if (! $disk->copy($application->guardian_signature_path, $target)) {
            throw ValidationException::withMessages(['decision' => 'The guardian signature could not be copied to the guardian profile.']);
        }

        return $target;
    }
}
