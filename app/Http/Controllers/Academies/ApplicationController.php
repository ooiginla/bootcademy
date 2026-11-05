<?php

namespace App\Http\Controllers\Academies;

use App\Academies\AcademyContext;
use App\Academies\Actions\ReviewApplication;
use App\Academies\Actions\SaveApplication;
use App\Enums\ApplicationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Academies\ReviewApplicationRequest;
use App\Http\Requests\Academies\SaveApplicationRequest;
use App\Models\AcademyCustomField;
use App\Models\Application;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApplicationController extends Controller
{
    public function index(Request $request, AcademyContext $context): Response
    {
        abort_unless($context->canManageAcademy(), 403);
        $academy = $context->academy();
        $search = trim((string) $request->query('search'));
        $status = (string) $request->query('status', 'all');
        $allowedStatuses = array_map(fn (ApplicationStatus $status): string => $status->value, ApplicationStatus::cases());
        if ($status !== 'all' && ! in_array($status, $allowedStatuses, true)) {
            $status = 'all';
        }

        return Inertia::render('academies/applications', [
            'applications' => $academy->applications()
                ->with([
                    'programme:id,name', 'branch:id,name', 'programmeCycle.cycle:id,name,type,starts_on,ends_on,duration_value,duration_unit',
                    'invoices.installments', 'invoices.cycle',
                ])
                ->when($status !== 'all', fn ($query) => $query->where('status', $status))
                ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                    $query->where('reference', 'like', "%$search%")
                        ->orWhere('first_name', 'like', "%$search%")
                        ->orWhere('last_name', 'like', "%$search%")
                        ->orWhere('guardian_email', 'like', "%$search%")
                        ->orWhere('guardian_phone', 'like', "%$search%");
                }))
                ->orderByDesc('created_at')->paginate(20)->withQueryString()
                ->through(fn (Application $application): array => [
                    'id' => $application->id, 'reference' => $application->reference,
                    'source' => $application->source, 'external_reference' => $application->external_reference,
                    'internal_identifier' => $application->internal_identifier,
                    'first_name' => $application->first_name, 'middle_name' => $application->middle_name,
                    'last_name' => $application->last_name, 'date_of_birth' => $application->date_of_birth?->toDateString(),
                    'age' => $application->date_of_birth?->age,
                    'gender' => $application->gender, 'phone' => $application->phone,
                    'whatsapp_number' => $application->whatsapp_number, 'home_address' => $application->home_address,
                    'city' => $application->city, 'state_region' => $application->state_region,
                    'country' => $application->country,
                    'guardian_name' => trim("{$application->guardian_first_name} {$application->guardian_last_name}"),
                    'guardian_first_name' => $application->guardian_first_name,
                    'guardian_last_name' => $application->guardian_last_name,
                    'guardian_email' => $application->guardian_email, 'guardian_phone' => $application->guardian_phone,
                    'guardian_whatsapp_number' => $application->guardian_whatsapp_number,
                    'guardian_relationship' => $application->guardian_relationship,
                    'has_guardian_signature' => $application->guardian_signature_path !== null,
                    'guardian_consent_at' => $application->guardian_consent_at?->toIso8601String(),
                    'privacy_policy_version' => $application->privacy_policy_version,
                    'programme_id' => $application->programme_id, 'programme_name' => $application->programme?->name,
                    'programme_cycle_id' => $application->programme_cycle_id,
                    'cycle_id' => $application->programmeCycle?->academy_cycle_id,
                    'cycle_name' => $application->programmeCycle?->cycle->name,
                    'branch_id' => $application->branch_id, 'branch_name' => $application->branch?->name,
                    'custom_fields' => $application->custom_fields ?? [], 'status' => $application->status->value,
                    'review_notes' => $application->review_notes, 'created_at' => $application->created_at?->toDateString(),
                    'invoices' => $application->invoices->map(fn ($invoice): array => [
                        'id' => $invoice->id, 'number' => $invoice->number, 'fee_name' => $invoice->fee_name,
                        'purpose' => $invoice->purpose->value,
                        'status' => $invoice->status->value, 'currency' => $invoice->currency,
                        'cycle_type' => $invoice->cycle->type->value,
                        'cycle_starts_on' => $invoice->cycle->starts_on?->toDateString(),
                        'cycle_ends_on' => $invoice->cycle->ends_on?->toDateString(),
                        'cycle_duration' => $invoice->cycle->duration_value,
                        'cycle_duration_unit' => $invoice->cycle->duration_unit?->value,
                        'total' => Money::fromMinor($invoice->total_minor),
                        'paid' => Money::fromMinor($invoice->paid_minor),
                        'balance' => Money::fromMinor($invoice->balanceMinor()),
                        'installments' => $invoice->installments->sortBy('sequence')->values()->map(fn ($installment): array => [
                            'name' => $installment->name, 'amount' => Money::fromMinor($installment->amount_minor),
                            'paid' => Money::fromMinor($installment->paid_minor),
                            'due_on' => $installment->due_on?->toDateString(),
                            'due_offset_days' => $installment->due_offset_days,
                        ]),
                    ])->values(),
                ]),
            'programmes' => $academy->programmes()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'branches' => $academy->branches()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'programme_cycles' => $academy->programmeCycles()->where('is_active', true)
                ->whereHas('cycle', fn ($query) => $query->where('status', 'active'))
                ->with(['cycle:id,name', 'programme:id,name', 'branch:id,name'])->get()->map(fn ($offering): array => [
                    'id' => $offering->id, 'programme_id' => $offering->programme_id,
                    'cycle_id' => $offering->academy_cycle_id, 'cycle_name' => $offering->cycle->name,
                    'name' => $offering->programme->name, 'branch_id' => $offering->branch_id,
                    'branch_name' => $offering->branch?->name,
                ])->values(),
            'active_cycles' => $academy->cycles()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'countries' => config('countries'),
            'custom_fields' => $academy->customFields()->where('entity_type', 'player')->where('is_active', true)->orderBy('sort_order')->get()
                ->map(fn (AcademyCustomField $field): array => [
                    'id' => $field->id, 'key' => $field->key, 'label' => $field->label, 'type' => $field->type->value,
                    'options' => $field->options ?? [], 'is_required' => $field->is_required,
                    'is_sensitive' => $field->is_sensitive, 'is_active' => $field->is_active, 'sort_order' => $field->sort_order,
                ]),
            'filters' => ['search' => $search, 'status' => $status],
        ]);
    }

    public function store(SaveApplicationRequest $request, AcademyContext $context, SaveApplication $action): RedirectResponse
    {
        $action->handle($context->academy(), $request->user(), $request->validated(), $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Application created.']);

        return back();
    }

    public function show(Application $application, AcademyContext $context): Response
    {
        abort_unless($context->canManageAcademy(), 403);
        abort_unless($application->academy_id === $context->id(), 404);
        $academy = $context->academy();
        $application->load([
            'programme:id,name', 'branch:id,name', 'programmeCycle.cycle',
            'invoices.installments', 'invoices.items', 'invoices.cycle',
            'payments.allocations.invoice',
        ]);

        return Inertia::render('academies/application-details', [
            'application' => $this->applicationDetails($application),
            'programmes' => $academy->programmes()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'branches' => $academy->branches()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'active_cycles' => $academy->cycles()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'programme_cycles' => $academy->programmeCycles()->where('is_active', true)
                ->whereHas('cycle', fn ($query) => $query->where('status', 'active'))
                ->with(['cycle:id,name', 'programme:id,name', 'branch:id,name'])->get()->map(fn ($offering): array => [
                    'id' => $offering->id, 'programme_id' => $offering->programme_id,
                    'cycle_id' => $offering->academy_cycle_id, 'cycle_name' => $offering->cycle->name,
                    'name' => $offering->programme->name, 'branch_id' => $offering->branch_id,
                    'branch_name' => $offering->branch?->name,
                ])->values(),
            'countries' => config('countries'),
            'custom_fields' => $academy->customFields()->where('entity_type', 'player')->where('is_active', true)->orderBy('sort_order')->get()
                ->map(fn (AcademyCustomField $field): array => [
                    'id' => $field->id, 'key' => $field->key, 'label' => $field->label, 'type' => $field->type->value,
                    'options' => $field->options ?? [], 'is_required' => $field->is_required,
                    'is_sensitive' => $field->is_sensitive, 'is_active' => $field->is_active, 'sort_order' => $field->sort_order,
                ]),
            'bank_details' => [
                'bank_name' => $academy->bank_name, 'account_name' => $academy->bank_account_name,
                'account_number' => $academy->bank_account_number,
            ],
        ]);
    }

    public function update(
        SaveApplicationRequest $request,
        Application $application,
        AcademyContext $context,
        SaveApplication $action,
    ): RedirectResponse {
        $action->handle($context->academy(), $request->user(), $request->validated(), $request, $application);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Application details updated.']);

        return back();
    }

    public function review(ReviewApplicationRequest $request, Application $application, AcademyContext $context, ReviewApplication $action): RedirectResponse
    {
        $action->handle($context->academy(), $application, $request->user(), $request->validated(), $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Application updated.']);

        return back();
    }

    /** @return array<string, mixed> */
    private function applicationDetails(Application $application): array
    {
        return [
            'id' => $application->id, 'reference' => $application->reference,
            'source' => $application->source, 'external_reference' => $application->external_reference,
            'internal_identifier' => $application->internal_identifier,
            'first_name' => $application->first_name, 'middle_name' => $application->middle_name,
            'last_name' => $application->last_name, 'date_of_birth' => $application->date_of_birth?->toDateString(),
            'age' => $application->date_of_birth?->age, 'gender' => $application->gender,
            'phone' => $application->phone, 'whatsapp_number' => $application->whatsapp_number,
            'home_address' => $application->home_address, 'city' => $application->city,
            'state_region' => $application->state_region, 'country' => $application->country,
            'guardian_name' => trim("{$application->guardian_first_name} {$application->guardian_last_name}"),
            'guardian_first_name' => $application->guardian_first_name, 'guardian_last_name' => $application->guardian_last_name,
            'guardian_email' => $application->guardian_email, 'guardian_phone' => $application->guardian_phone,
            'guardian_whatsapp_number' => $application->guardian_whatsapp_number,
            'guardian_relationship' => $application->guardian_relationship,
            'has_guardian_signature' => $application->guardian_signature_path !== null,
            'guardian_consent_at' => $application->guardian_consent_at?->toIso8601String(),
            'privacy_policy_version' => $application->privacy_policy_version,
            'programme_id' => $application->programme_id, 'programme_name' => $application->programme?->name,
            'programme_cycle_id' => $application->programme_cycle_id,
            'cycle_id' => $application->programmeCycle?->academy_cycle_id,
            'cycle_name' => $application->programmeCycle?->cycle->name,
            'branch_id' => $application->branch_id, 'branch_name' => $application->branch?->name,
            'custom_fields' => $application->custom_fields ?? [], 'status' => $application->status->value,
            'review_notes' => $application->review_notes, 'created_at' => $application->created_at?->toDateString(),
            'invoices' => $application->invoices->map(fn ($invoice): array => [
                'id' => $invoice->id, 'number' => $invoice->number, 'fee_name' => $invoice->fee_name,
                'purpose' => $invoice->purpose->value,
                'status' => $invoice->status->value, 'currency' => $invoice->currency,
                'cycle_type' => $invoice->cycle->type->value,
                'cycle_starts_on' => $invoice->cycle->starts_on?->toDateString(),
                'cycle_ends_on' => $invoice->cycle->ends_on?->toDateString(),
                'cycle_duration' => $invoice->cycle->duration_value,
                'cycle_duration_unit' => $invoice->cycle->duration_unit?->value,
                'total' => Money::fromMinor($invoice->total_minor),
                'paid' => Money::fromMinor($invoice->paid_minor), 'balance' => Money::fromMinor($invoice->balanceMinor()),
                'items' => $invoice->items->map(fn ($item): array => [
                    'description' => $item->description, 'amount' => Money::fromMinor($item->amount_minor),
                ]),
                'installments' => $invoice->installments->sortBy('sequence')->values()->map(fn ($installment): array => [
                    'name' => $installment->name, 'amount' => Money::fromMinor($installment->amount_minor),
                    'paid' => Money::fromMinor($installment->paid_minor),
                    'due_on' => $installment->due_on?->toDateString(), 'due_offset_days' => $installment->due_offset_days,
                ]),
            ])->values(),
            'payments' => $application->payments->map(fn ($payment): array => [
                'id' => $payment->id, 'reference' => $payment->reference,
                'external_reference' => $payment->external_reference, 'currency' => $payment->currency,
                'amount' => Money::fromMinor($payment->amount_minor), 'method' => $payment->method->value,
                'status' => $payment->status->value, 'received_on' => $payment->received_on->toDateString(),
            ])->values(),
        ];
    }
}
