<?php

namespace App\Http\Controllers\Academies;

use App\Academies\AcademyContext;
use App\Academies\Actions\IssuePlayerCycleFeeInvoice;
use App\Academies\Actions\SaveEnrollment;
use App\Academies\Actions\SaveGuardian;
use App\Academies\Actions\SavePlayer;
use App\Academies\Actions\SoftDeleteAcademyRecord;
use App\Http\Controllers\Controller;
use App\Http\Requests\Academies\BookPlayerCycleFeeRequest;
use App\Http\Requests\Academies\SaveEnrollmentRequest;
use App\Http\Requests\Academies\SaveGuardianRequest;
use App\Http\Requests\Academies\SavePlayerRequest;
use App\Models\AcademyCustomField;
use App\Models\AcademyCycle;
use App\Models\AcademyCycleFee;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\Player;
use App\Models\PlayerCustomFieldValue;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlayerController extends Controller
{
    public function show(Player $player, AcademyContext $context): Response
    {
        abort_unless($context->canManageAcademy(), 403);
        abort_unless($player->academy_id === $context->id(), 404);
        $player->load([
            'guardians', 'customFieldValues.field', 'enrollments.programme', 'enrollments.branch', 'enrollments.cycle',
            'invoices.cycle', 'invoices.programmeCycle.programme', 'invoices.cycleFee',
            'invoices.installments', 'payments.allocations.invoice',
        ]);
        $cycleIds = $player->enrollments->pluck('academy_cycle_id')
            ->merge($player->invoices->pluck('academy_cycle_id'))->filter()->unique()->values();
        $cycles = $context->academy()->cycles()->whereIn('id', $cycleIds)->get()->keyBy('id');
        $openInvoices = $player->invoices->reject(fn ($invoice): bool => $invoice->status->value === 'void');
        $activeEnrollments = $player->enrollments->filter(fn (Enrollment $enrollment): bool => $enrollment->status->value === 'active');
        $invoicedFeeIds = $player->invoices->pluck('academy_cycle_fee_id')->filter();
        $bookableFees = $context->academy()->cycleFees()->where('is_active', true)
            ->with(['cycle:id,name', 'programmes:id'])->orderBy('name')->get()
            ->filter(function ($fee) use ($activeEnrollments, $invoicedFeeIds): bool {
                if ($invoicedFeeIds->contains($fee->id)) {
                    return false;
                }

                return $activeEnrollments->contains(fn (Enrollment $enrollment): bool => $enrollment->academy_cycle_id === $fee->academy_cycle_id
                    && ($fee->applies_to_all_programmes || $fee->programmes->contains('id', $enrollment->programme_id))
                );
            });

        return Inertia::render('academies/player-details', [
            'player' => [
                'id' => $player->id, 'internal_identifier' => $player->internal_identifier,
                'name' => trim("{$player->first_name} {$player->middle_name} {$player->last_name}"),
                'first_name' => $player->first_name, 'middle_name' => $player->middle_name,
                'last_name' => $player->last_name,
                'date_of_birth' => $player->date_of_birth?->toDateString(),
                'age' => $player->date_of_birth?->age, 'gender' => $player->gender,
                'phone' => $player->phone, 'whatsapp_number' => $player->whatsapp_number,
                'home_address' => $player->home_address, 'city' => $player->city,
                'state_region' => $player->state_region, 'country' => $player->country,
                'status' => $player->status->value,
                'total_billed' => Money::fromMinor((int) $openInvoices->sum('total_minor')),
                'total_paid' => Money::fromMinor((int) $openInvoices->sum('paid_minor')),
                'total_owed' => Money::fromMinor((int) $openInvoices->sum(fn ($invoice) => $invoice->personalBalanceMinor())),
                'currency' => $context->academy()->currency,
                'guardians' => $player->guardians->map(fn (Guardian $guardian): array => [
                    'id' => $guardian->id,
                    'first_name' => $guardian->first_name, 'last_name' => $guardian->last_name,
                    'name' => trim("{$guardian->first_name} {$guardian->last_name}"),
                    'relationship' => (string) $guardian->getRelation('pivot')->getAttribute('relationship'),
                    'email' => $guardian->email, 'phone' => $guardian->phone,
                    'whatsapp_number' => $guardian->whatsapp_number,
                    'is_primary' => (bool) $guardian->getRelation('pivot')->getAttribute('is_primary'),
                    'is_emergency_contact' => (bool) $guardian->getRelation('pivot')->getAttribute('is_emergency_contact'),
                    'has_signature' => $guardian->signature_path !== null,
                ])->values(),
                'custom_fields' => $player->customFieldValues->map(fn (PlayerCustomFieldValue $value): array => [
                    'label' => $value->field->label, 'value' => $value->value,
                ])->values(),
                'cycles' => $cycleIds->map(function (int $cycleId) use ($player, $cycles): array {
                    $cycle = $cycles->get($cycleId);
                    $invoices = $player->invoices->where('academy_cycle_id', $cycleId)->values();
                    $billableInvoices = $invoices->reject(fn ($invoice) => $invoice->status->value === 'void');

                    return [
                        'id' => $cycleId, 'name' => $cycle instanceof AcademyCycle ? $cycle->name : 'Unknown cycle',
                        'type' => $cycle instanceof AcademyCycle ? $cycle->type->value : 'fixed',
                        'status' => $cycle instanceof AcademyCycle ? $cycle->status->value : 'closed',
                        'billed' => Money::fromMinor((int) $billableInvoices->sum('total_minor')),
                        'paid' => Money::fromMinor((int) $billableInvoices->sum('paid_minor')),
                        'owed' => Money::fromMinor((int) $billableInvoices->sum(fn ($invoice) => $invoice->personalBalanceMinor())),
                        'enrollments' => $player->enrollments->where('academy_cycle_id', $cycleId)->values()->map(fn ($enrollment): array => [
                            'id' => $enrollment->id, 'programme_id' => $enrollment->programme_id,
                            'programme' => $enrollment->programme->name, 'branch_id' => $enrollment->branch_id,
                            'branch' => $enrollment->branch?->name,
                            'status' => $enrollment->status->value, 'starts_on' => $enrollment->starts_on->toDateString(),
                            'ends_on' => $enrollment->ends_on?->toDateString(),
                        ]),
                        'invoices' => $invoices->map(fn ($invoice): array => [
                            'id' => $invoice->id, 'number' => $invoice->number, 'purpose' => $invoice->purpose->value,
                            'fee_name' => $invoice->fee_name ?? $invoice->cycleFee?->name ?? match ($invoice->purpose->value) {
                                'application' => 'Application fee',
                                'programme' => 'Programme fee',
                                default => 'Custom fee',
                            },
                            'programme' => $invoice->programmeCycle?->programme?->name ?? $invoice->cycleFee?->name ?? 'Custom fee',
                            'status' => $invoice->status->value,
                            'currency' => $invoice->currency, 'total' => Money::fromMinor($invoice->total_minor),
                            'paid' => Money::fromMinor($invoice->paid_minor), 'written_off' => Money::fromMinor($invoice->written_off_minor),
                            'balance' => Money::fromMinor($invoice->status->value === 'void' ? 0 : $invoice->personalBalanceMinor()),
                            'scholarship_concession' => Money::fromMinor($invoice->scholarship_concession_minor),
                            'sponsor_responsibility' => Money::fromMinor($invoice->sponsor_responsibility_minor),
                            'sponsor_paid' => Money::fromMinor($invoice->sponsor_paid_minor),
                            'cycle_type' => $invoice->cycle->type->value,
                            'cycle_starts_on' => $invoice->cycle->starts_on?->toDateString(),
                            'cycle_ends_on' => $invoice->cycle->ends_on?->toDateString(),
                            'cycle_duration' => $invoice->cycle->duration_value,
                            'cycle_duration_unit' => $invoice->cycle->duration_unit?->value,
                            'installments' => $invoice->installments->sortBy('sequence')->values()->map(fn ($installment): array => [
                                'name' => $installment->name, 'amount' => Money::fromMinor($installment->amount_minor),
                                'paid' => Money::fromMinor($installment->paid_minor), 'due_on' => $installment->due_on?->toDateString(),
                                'due_offset_days' => $installment->due_offset_days,
                            ]),
                        ]),
                        'payments' => $player->payments->filter(fn ($payment) => $payment->allocations->contains(
                            fn ($allocation) => $allocation->invoice?->academy_cycle_id === $cycleId,
                        ))->values()->map(fn ($payment): array => [
                            'id' => $payment->id, 'reference' => $payment->reference,
                            'amount' => Money::fromMinor($payment->amount_minor),
                            'reversed' => Money::fromMinor($payment->reversed_minor),
                            'net' => Money::fromMinor($payment->amount_minor - $payment->reversed_minor),
                            'method' => $payment->method->value,
                            'status' => $payment->status->value, 'received_on' => $payment->received_on->toDateString(),
                        ]),
                    ];
                }),
                'custom_field_values' => $player->customFieldValues->mapWithKeys(fn (PlayerCustomFieldValue $value): array => [$value->field->key => $value->value]),
            ],
            'programmes' => $context->academy()->programmes()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'branches' => $context->academy()->branches()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'custom_fields' => $context->academy()->customFields()->where('entity_type', 'player')->where('is_active', true)->orderBy('sort_order')->get(),
            'bookable_fees' => $bookableFees->map(fn ($fee): array => [
                'id' => $fee->id, 'name' => $fee->name, 'cycle_name' => $fee->cycle->name,
                'amount' => Money::fromMinor($fee->amount_minor), 'currency' => $fee->currency,
            ])->values(),
        ]);
    }

    public function index(Request $request, AcademyContext $context): Response
    {
        abort_unless($context->canManageAcademy(), 403);
        $academy = $context->academy();
        $playerSearch = trim((string) $request->query('players_search'));
        $guardianSearch = trim((string) $request->query('guardians_search'));
        $tab = in_array($request->query('tab'), ['students', 'guardians', 'fields'], true)
            ? (string) $request->query('tab')
            : 'students';

        return Inertia::render('academies/players-directory', [
            'players' => $academy->players()
                ->with([
                    'guardians:id,first_name,last_name,email,phone,whatsapp_number,signature_path',
                    'enrollments.programme:id,name',
                    'enrollments.branch:id,name',
                    'customFieldValues.field',
                ])
                ->when($playerSearch !== '', fn ($query) => $query->where(function ($query) use ($playerSearch): void {
                    $query->where('first_name', 'like', "%$playerSearch%")
                        ->orWhere('middle_name', 'like', "%$playerSearch%")
                        ->orWhere('last_name', 'like', "%$playerSearch%")
                        ->orWhere('internal_identifier', 'like', "%$playerSearch%");
                }))
                ->orderBy('last_name')->orderBy('first_name')
                ->paginate(15, ['*'], 'players_page')->withQueryString()
                ->through(fn (Player $player): array => [
                    'id' => $player->id,
                    'internal_identifier' => $player->internal_identifier,
                    'first_name' => $player->first_name,
                    'middle_name' => $player->middle_name,
                    'last_name' => $player->last_name,
                    'date_of_birth' => $player->date_of_birth?->toDateString(),
                    'gender' => $player->gender,
                    'phone' => $player->phone,
                    'whatsapp_number' => $player->whatsapp_number,
                    'home_address' => $player->home_address,
                    'city' => $player->city,
                    'state_region' => $player->state_region,
                    'country' => $player->country,
                    'status' => $player->status->value,
                    'guardians' => $player->guardians->map(fn (Guardian $guardian): array => [
                        'id' => $guardian->id,
                        'name' => "$guardian->first_name $guardian->last_name",
                        'email' => $guardian->email,
                        'phone' => $guardian->phone,
                        'relationship' => (string) $guardian->getRelation('pivot')->getAttribute('relationship'),
                    ])->values(),
                    'enrollments' => $player->enrollments->map(fn (Enrollment $enrollment): array => [
                        'id' => $enrollment->id,
                        'programme_id' => $enrollment->programme_id,
                        'programme_name' => $enrollment->programme->name,
                        'branch_id' => $enrollment->branch_id,
                        'branch_name' => $enrollment->branch?->name,
                        'status' => $enrollment->status->value,
                        'starts_on' => $enrollment->starts_on->toDateString(),
                        'ends_on' => $enrollment->ends_on?->toDateString(),
                    ])->values(),
                    'custom_fields' => $player->customFieldValues
                        ->mapWithKeys(fn (PlayerCustomFieldValue $value): array => [$value->field->key => $value->value]),
                ]),
            'guardians' => $academy->guardians()
                ->when($guardianSearch !== '', fn ($query) => $query->where(function ($query) use ($guardianSearch): void {
                    $query->where('first_name', 'like', "%$guardianSearch%")
                        ->orWhere('last_name', 'like', "%$guardianSearch%")
                        ->orWhere('email', 'like', "%$guardianSearch%")
                        ->orWhere('phone', 'like', "%$guardianSearch%");
                }))
                ->orderBy('last_name')->orderBy('first_name')
                ->paginate(15, ['*'], 'guardians_page')->withQueryString()
                ->through(fn (Guardian $guardian): array => [
                    'id' => $guardian->id,
                    'first_name' => $guardian->first_name,
                    'last_name' => $guardian->last_name,
                    'email' => $guardian->email,
                    'phone' => $guardian->phone,
                    'whatsapp_number' => $guardian->whatsapp_number,
                    'has_signature' => $guardian->signature_path !== null,
                ]),
            'programmes' => $academy->programmes()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'branches' => $academy->branches()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'custom_fields' => $academy->customFields()
                ->where('entity_type', 'player')
                ->orderBy('sort_order')->orderBy('label')->get()
                ->map(fn (AcademyCustomField $field): array => [
                    'id' => $field->id,
                    'key' => $field->key,
                    'label' => $field->label,
                    'type' => $field->type->value,
                    'options' => $field->options ?? [],
                    'is_required' => $field->is_required,
                    'is_sensitive' => $field->is_sensitive,
                    'is_active' => $field->is_active,
                    'sort_order' => $field->sort_order,
                ]),
            'active_tab' => $tab,
            'filters' => [
                'players_search' => $playerSearch,
                'guardians_search' => $guardianSearch,
            ],
        ]);
    }

    public function searchGuardians(Request $request, AcademyContext $context): JsonResponse
    {
        abort_unless($context->canManageAcademy(), 403);
        $query = trim((string) $request->query('q'));
        abort_if(mb_strlen($query) > 100, 422, 'Search text is too long.');

        if (mb_strlen($query) < 2) {
            return response()->json(['data' => []]);
        }

        $guardians = $context->academy()->guardians()
            ->where(function ($builder) use ($query): void {
                $builder->where('first_name', 'like', "%$query%")
                    ->orWhere('last_name', 'like', "%$query%")
                    ->orWhere('email', 'like', "%$query%")
                    ->orWhere('phone', 'like', "%$query%");
            })
            ->orderBy('last_name')->orderBy('first_name')->limit(20)->get()
            ->map(fn (Guardian $guardian): array => [
                'id' => $guardian->id,
                'name' => "$guardian->first_name $guardian->last_name",
                'email' => $guardian->email,
                'phone' => $guardian->phone,
            ]);

        return response()->json(['data' => $guardians]);
    }

    public function store(SavePlayerRequest $request, AcademyContext $context, SavePlayer $action): RedirectResponse
    {
        $action->handle($context->academy(), $request->user(), $request->validated(), $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Player profile created.']);

        return back();
    }

    public function update(SavePlayerRequest $request, Player $player, AcademyContext $context, SavePlayer $action): RedirectResponse
    {
        $action->handle($context->academy(), $request->user(), $request->validated(), $request, $player);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Player profile updated.']);

        return back();
    }

    public function storeGuardian(SaveGuardianRequest $request, AcademyContext $context, SaveGuardian $action): RedirectResponse
    {
        $action->handle($context->academy(), $request->user(), $request->validated(), $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Guardian added.']);

        return back();
    }

    public function updateGuardian(SaveGuardianRequest $request, Guardian $guardian, AcademyContext $context, SaveGuardian $action): RedirectResponse
    {
        $action->handle($context->academy(), $request->user(), $request->validated(), $request, $guardian);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Guardian updated.']);

        return back();
    }

    public function storeEnrollment(SaveEnrollmentRequest $request, Player $player, AcademyContext $context, SaveEnrollment $action): RedirectResponse
    {
        $action->handle($context->academy(), $player, $request->user(), $request->validated(), $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Programme enrollment added.']);

        return back();
    }

    public function updateEnrollment(SaveEnrollmentRequest $request, Player $player, Enrollment $enrollment, AcademyContext $context, SaveEnrollment $action): RedirectResponse
    {
        $action->handle($context->academy(), $player, $request->user(), $request->validated(), $request, $enrollment);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Programme enrollment updated.']);

        return back();
    }

    public function bookFee(BookPlayerCycleFeeRequest $request, Player $player, AcademyContext $context, IssuePlayerCycleFeeInvoice $issue): RedirectResponse
    {
        $fee = AcademyCycleFee::query()->where('academy_id', $context->id())->findOrFail($request->integer('fee_id'));
        $issue->handle($context->academy(), $player, $fee, $request->user(), $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Fee invoice booked to player.']);

        return back();
    }

    public function destroy(Request $request, Player $player, AcademyContext $context, SoftDeleteAcademyRecord $delete): RedirectResponse
    {
        abort_unless($context->canManageAcademy(), 403);
        $label = mb_strtolower($context->academy()->member_label_singular);
        $delete->handle($context->academy(), $request->user(), $player, $label, trim($player->first_name.' '.$player->last_name), $player->only(['internal_identifier', 'status']), $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => ucfirst($label).' deleted.']);

        return to_route('academy.players.index');
    }
}
