<?php

namespace App\Http\Controllers\Api\V1;

use App\Academies\Actions\SaveApplication;
use App\Academies\ApplicationIntakeAvailability;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreApplicationRequest;
use App\Models\Academy;
use App\Models\AcademyCustomField;
use App\Models\Application;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;

class ApplicationController extends Controller
{
    public function store(StoreApplicationRequest $request, SaveApplication $save, ApplicationIntakeAvailability $availability): JsonResponse
    {
        $academy = $this->academy($request);
        $data = $request->validated();
        $intake = $availability->query($academy)->with('programmeCycle')->whereKey($data['application_intake_id'])->firstOrFail();
        $data['programme_cycle_id'] = $intake->programme_cycle_id;
        $data['programme_id'] = $intake->programmeCycle->programme_id;
        $data['cohort_id'] = $intake->cohort_id;
        $data['branch_id'] = $intake->branch_id ?? $data['preferred_branch_id'] ?? $intake->programmeCycle->branch_id;
        unset($data['preferred_branch_id']);
        $externalReference = (string) $data['external_reference'];
        $submissionHash = $this->submissionHash($data);
        $existing = $academy->applications()->where('external_reference', $externalReference)->first();
        if ($existing !== null) {
            return $this->existingResponse($existing, $submissionHash);
        }
        $duplicate = $this->findDuplicate($academy, $data);
        if ($duplicate !== null) {
            return response()->json([
                'message' => 'A matching application or student profile already exists.',
                'errors' => ['application' => ['Review the existing record before submitting another application.']],
                'existing_reference' => $duplicate,
            ], 409);
        }

        $data = Arr::except($data, ['consent_accepted']);
        $data['source'] = 'api';
        $data['submission_hash'] = $submissionHash;
        $data['guardian_consent_at'] = $academy->collect_guardian_information ? now() : null;

        try {
            $application = $save->handle($academy, null, $data, $request);
        } catch (QueryException $exception) {
            $existing = $academy->applications()->where('external_reference', $externalReference)->first();
            if ($existing === null) {
                throw $exception;
            }

            return $this->existingResponse($existing, $submissionHash);
        }

        return $this->applicationResponse($application, false, 201);
    }

    public function show(Request $request): JsonResponse
    {
        $validated = $request->validate(['external_reference' => ['required', 'string', 'max:120']]);
        $application = $this->academy($request)->applications()
            ->where('external_reference', $validated['external_reference'])->firstOrFail();

        return $this->applicationResponse($application, true);
    }

    public function configuration(Request $request): JsonResponse
    {
        $academy = $this->academy($request);

        return response()->json(['data' => [
            'academy' => [
                'name' => $academy->name,
                'terminology' => [
                    'singular' => $academy->member_label_singular, 'plural' => $academy->member_label_plural,
                    'guardian_singular' => $academy->guardian_label_singular,
                    'guardian_plural' => $academy->guardian_label_plural,
                ],
                'collect_guardian_information' => $academy->collect_guardian_information,
                'currency' => $academy->currency,
            ],
            'application_intakes' => app(ApplicationIntakeAvailability::class)->query($academy)
                ->with(['programmeCycle.cycle:id,name,type,starts_on,ends_on,duration_value,duration_unit', 'programmeCycle.programme:id,name', 'programmeCycle.branch:id,name', 'programmeCycle.feeItems', 'cohort:id,name', 'branch:id,name'])
                ->get()->map(function ($intake): array {
                    $offering = $intake->programmeCycle;

                    return [
                        'id' => $intake->id, 'programme_cycle_id' => $offering->id,
                        'programme_id' => $offering->programme_id, 'programme_name' => $offering->programme->name,
                        'cycle_id' => $offering->academy_cycle_id, 'cycle_name' => $offering->cycle->name,
                        'cycle_type' => $offering->cycle->type->value,
                        'cohort_id' => $intake->cohort_id, 'cohort_name' => $intake->cohort?->name,
                        'branch_id' => $intake->branch_id,
                        'branch_name' => $intake->branch?->name ?? $offering->branch?->name,
                        'currency' => $offering->currency,
                        'application_fee_minor' => $offering->application_fee_minor,
                        'programme_fee_minor' => (int) $offering->feeItems
                            ->filter(fn ($item): bool => $item->cohort_id === null || $item->cohort_id === $intake->cohort_id)
                            ->sum('amount_minor'),
                        'opens_on' => $intake->opens_on?->toDateString(), 'closes_on' => $intake->closes_on?->toDateString(),
                    ];
                })->values(),
            'branches' => $academy->branches()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'custom_fields' => $academy->customFields()->where('entity_type', 'player')->where('is_active', true)
                ->orderBy('sort_order')->get()->map(fn (AcademyCustomField $field): array => [
                    'key' => $field->key, 'label' => $field->label, 'type' => $field->type->value,
                    'options' => $field->options ?? [], 'required' => $field->is_required,
                    'sensitive' => $field->is_sensitive,
                ])->values(),
            'countries' => config('countries'),
        ]]);
    }

    private function academy(Request $request): Academy
    {
        $academy = $request->attributes->get('api_academy');
        abort_unless($academy instanceof Academy, 401);

        return $academy;
    }

    /** @param array<string, mixed> $data */
    private function submissionHash(array $data): string
    {
        $signature = $data['guardian_signature'] ?? null;
        $hashable = Arr::except($data, ['guardian_signature']);
        $hashable['guardian_signature_sha256'] = $signature instanceof UploadedFile
            ? hash_file('sha256', $signature->getRealPath())
            : null;
        $hashable = $this->sortRecursively($hashable);

        return hash('sha256', json_encode($hashable, JSON_THROW_ON_ERROR));
    }

    /** @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private function sortRecursively(array $values): array
    {
        ksort($values);
        foreach ($values as &$value) {
            if (is_array($value)) {
                $value = $this->sortRecursively($value);
            }
        }

        return $values;
    }

    private function existingResponse(Application $application, string $submissionHash): JsonResponse
    {
        if (! hash_equals((string) $application->submission_hash, $submissionHash)) {
            return response()->json([
                'message' => 'This external_reference was already used with different application data.',
                'errors' => ['external_reference' => ['Use a new external reference or resend the original payload.']],
            ], 409);
        }

        return $this->applicationResponse($application, true);
    }

    /** @param array<string, mixed> $data */
    private function findDuplicate(Academy $academy, array $data): ?string
    {
        $applications = $academy->applications()
            ->where('first_name', $data['first_name'])->where('last_name', $data['last_name'])
            ->where('status', '!=', 'rejected');
        $players = $academy->players()
            ->where('first_name', $data['first_name'])->where('last_name', $data['last_name']);
        if (($data['date_of_birth'] ?? null) !== null) {
            $applications->whereDate('date_of_birth', $data['date_of_birth']);
            $players->whereDate('date_of_birth', $data['date_of_birth']);
        } elseif (($data['guardian_phone'] ?? null) !== null) {
            $applications->where('guardian_phone', $data['guardian_phone']);
            $players->whereHas('guardians', fn ($query) => $query->where('phone', $data['guardian_phone']));
        } else {
            return null;
        }
        $application = $applications->first();
        if ($application !== null) {
            return $application->reference;
        }

        return $players->exists() ? 'existing-student-profile' : null;
    }

    private function applicationResponse(Application $application, bool $existing, int $status = 200): JsonResponse
    {
        return response()->json(['data' => [
            'id' => $application->id, 'reference' => $application->reference,
            'external_reference' => $application->external_reference, 'status' => $application->status->value,
            'application_intake_id' => $application->application_intake_id, 'cohort_id' => $application->cohort_id,
            'existing' => $existing, 'submitted_at' => $application->created_at?->toIso8601String(),
        ]], $status);
    }
}
