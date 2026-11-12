<?php

namespace App\Http\Controllers\Academies;

use App\Academies\AcademyContext;
use App\Academies\Actions\ApplyScholarshipAward;
use App\Academies\Actions\RecordSponsorContribution;
use App\Enums\AcademyCycleType;
use App\Enums\AcademyInvoiceStatus;
use App\Enums\AcademyPaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\AcademyInvoice;
use App\Models\ScholarshipAward;
use App\Models\ScholarshipInvoiceAllocation;
use App\Models\ScholarshipScheme;
use App\Models\Sponsor;
use App\Models\SponsorCommitment;
use App\Platform\AuditLogger;
use App\Support\Money;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ScholarshipController extends Controller
{
    public function index(AcademyContext $context): Response
    {
        abort_unless($context->canManageFinance(), 403);
        $academy = $context->academy();
        $allocations = $academy->scholarshipAwards()->where('status', 'active')
            ->withSum('allocations', 'amount_minor')->get();
        $sponsorOutstanding = ScholarshipInvoiceAllocation::query()->where('academy_id', $academy->id)
            ->where('funding_type', 'sponsor')->where('status', 'active')
            ->selectRaw('COALESCE(SUM(amount_minor - funded_minor), 0) AS outstanding')->value('outstanding');

        return Inertia::render('academies/scholarships', [
            'summary' => [
                'currency' => $academy->currency,
                'beneficiaries' => $allocations->pluck('player_id')->unique()->count(),
                'awarded' => Money::fromMinor((int) $allocations->sum('allocations_sum_amount_minor')),
                'academy_concessions' => Money::fromMinor((int) $academy->invoices()->sum('scholarship_concession_minor')),
                'sponsor_outstanding' => Money::fromMinor((int) $sponsorOutstanding),
            ],
            'sponsors' => $academy->sponsors()->with(['commitments.cycle'])->orderBy('name')->get()->map(fn (Sponsor $sponsor): array => [
                'id' => $sponsor->id, 'name' => $sponsor->name, 'type' => $sponsor->type,
                'contact_name' => $sponsor->contact_name, 'email' => $sponsor->email, 'phone' => $sponsor->phone,
                'address' => $sponsor->address, 'notes' => $sponsor->notes, 'status' => $sponsor->status,
                'commitments' => $sponsor->commitments->map(fn (SponsorCommitment $commitment): array => [
                    'id' => $commitment->id, 'sponsor_id' => $commitment->sponsor_id,
                    'academy_cycle_id' => $commitment->academy_cycle_id, 'name' => $commitment->name,
                    'agreement_reference' => $commitment->agreement_reference, 'benefits' => $commitment->benefits,
                    'cycle_name' => $commitment->cycle?->name, 'currency' => $commitment->currency,
                    'committed' => Money::fromMinor($commitment->committed_minor),
                    'received' => Money::fromMinor($commitment->received_minor),
                    'remaining' => Money::fromMinor($commitment->committed_minor - $commitment->received_minor),
                    'starts_on' => $commitment->starts_on?->toDateString(), 'ends_on' => $commitment->ends_on?->toDateString(),
                    'renewal_on' => $commitment->renewal_on?->toDateString(), 'status' => $commitment->status,
                    'notes' => $commitment->notes,
                ])->values(),
            ]),
            'schemes' => $academy->scholarshipSchemes()->with(['sponsor', 'cycle', 'term', 'programme', 'branch'])
                ->withCount('awards')->orderByDesc('is_active')->orderBy('name')->get()->map(fn (ScholarshipScheme $scheme): array => [
                    'id' => $scheme->id, 'name' => $scheme->name, 'funding_type' => $scheme->funding_type,
                    'sponsor_id' => $scheme->sponsor_id, 'sponsor_name' => $scheme->sponsor?->name,
                    'coverage_type' => $scheme->coverage_type,
                    'percentage' => $scheme->percentage_basis_points !== null ? $scheme->percentage_basis_points / 100 : null,
                    'fixed_amount' => $scheme->fixed_amount_minor !== null ? Money::fromMinor($scheme->fixed_amount_minor) : null,
                    'currency' => $scheme->currency, 'academy_cycle_id' => $scheme->academy_cycle_id,
                    'cycle_name' => $scheme->cycle?->name, 'academy_cycle_term_id' => $scheme->academy_cycle_term_id,
                    'term_name' => $scheme->term?->name, 'programme_id' => $scheme->programme_id,
                    'programme_name' => $scheme->programme?->name, 'branch_id' => $scheme->branch_id,
                    'branch_name' => $scheme->branch?->name, 'starts_on' => $scheme->starts_on?->toDateString(),
                    'ends_on' => $scheme->ends_on?->toDateString(), 'description' => $scheme->description,
                    'is_active' => $scheme->is_active, 'awards_count' => $scheme->awards_count,
                ]),
            'awards' => $academy->scholarshipAwards()->with([
                'scheme.sponsor', 'player', 'enrollment.programme', 'commitment',
                'allocations.invoice', 'allocations.contributions',
            ])->orderByDesc('approved_at')->get()->map(fn (ScholarshipAward $award): array => [
                'id' => $award->id, 'scheme_id' => $award->scholarship_scheme_id,
                'scheme_name' => $award->scheme->name, 'funding_type' => $award->scheme->funding_type,
                'sponsor_name' => $award->scheme->sponsor?->name,
                'player_id' => $award->player_id,
                'player_name' => trim("{$award->player->first_name} {$award->player->middle_name} {$award->player->last_name}"),
                'programme_name' => $award->enrollment?->programme?->name,
                'commitment_id' => $award->sponsor_commitment_id, 'commitment_name' => $award->commitment?->name,
                'status' => $award->status, 'starts_on' => $award->starts_on?->toDateString(),
                'ends_on' => $award->ends_on?->toDateString(), 'notes' => $award->notes,
                'allocations' => $award->allocations->map(fn (ScholarshipInvoiceAllocation $allocation): array => [
                    'id' => $allocation->id, 'invoice_id' => $allocation->academy_invoice_id,
                    'invoice_number' => $allocation->invoice->number, 'fee_name' => $allocation->invoice->fee_name,
                    'currency' => $allocation->invoice->currency, 'amount' => Money::fromMinor($allocation->amount_minor),
                    'funded' => Money::fromMinor($allocation->funded_minor),
                    'outstanding' => Money::fromMinor($allocation->amount_minor - $allocation->funded_minor),
                    'status' => $allocation->status,
                ])->values(),
            ]),
            'players' => $academy->players()->where('status', 'active')->orderBy('last_name')->orderBy('first_name')->get()
                ->map(fn ($player): array => ['id' => $player->id, 'name' => trim("{$player->first_name} {$player->middle_name} {$player->last_name}")]),
            'enrollments' => $academy->enrollments()->where('status', 'active')->with(['player', 'programme', 'cycle'])->get()
                ->map(fn ($enrollment): array => [
                    'id' => $enrollment->id, 'player_id' => $enrollment->player_id,
                    'label' => $enrollment->programme->name.' · '.($enrollment->cycle?->name ?? 'No cycle'),
                ]),
            'invoices' => $academy->invoices()->whereNotNull('player_id')
                ->whereNotIn('status', [AcademyInvoiceStatus::Void->value, AcademyInvoiceStatus::Paid->value, AcademyInvoiceStatus::WrittenOff->value, AcademyInvoiceStatus::Settled->value])
                ->with(['player', 'cycle', 'programme'])->orderByDesc('issued_at')->get()
                ->filter(fn (AcademyInvoice $invoice): bool => $invoice->personalBalanceMinor() > 0)
                ->map(fn (AcademyInvoice $invoice): array => [
                    'id' => $invoice->id, 'player_id' => $invoice->player_id,
                    'label' => $invoice->number.' · '.($invoice->fee_name ?? 'Fee').' · '.Money::format($invoice->currency, Money::fromMinor($invoice->personalBalanceMinor())),
                    'cycle_id' => $invoice->academy_cycle_id, 'programme_id' => $invoice->programme_id,
                    'branch_id' => $invoice->branch_id,
                ])->values(),
            'cycles' => $academy->cycles()->with(['terms' => fn ($query) => $query
                ->where('is_active', true)
                ->whereHas('cycle', fn ($cycle) => $cycle->where('type', AcademyCycleType::Fixed->value))
                ->orderBy('sort_order')])->orderByDesc('starts_on')->get(),
            'programmes' => $academy->programmes()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'branches' => $academy->branches()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'payment_methods' => collect(AcademyPaymentMethod::cases())->reject(fn ($method) => $method === AcademyPaymentMethod::Paystack)
                ->map(fn ($method): array => ['value' => $method->value, 'label' => str($method->value)->replace('_', ' ')->title()->toString()])->values(),
            'today' => now($academy->timezone)->toDateString(),
        ]);
    }

    public function storeSponsor(Request $request, AcademyContext $context, AuditLogger $audit): RedirectResponse
    {
        $this->authorizeFinance($context);
        $data = $request->validate($this->sponsorRules());
        $sponsor = Sponsor::query()->create([...$data, 'academy_id' => $context->id(), 'created_by_user_id' => $request->user()->id]);
        $audit->record('academy.sponsor.created', $request->user(), $context->academy(), $sponsor, request: $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Sponsor created.']);

        return back();
    }

    public function updateSponsor(Request $request, Sponsor $sponsor, AcademyContext $context, AuditLogger $audit): RedirectResponse
    {
        $this->authorizeFinance($context);
        abort_unless($sponsor->academy_id === $context->id(), 404);
        $sponsor->update($request->validate($this->sponsorRules()));
        $audit->record('academy.sponsor.updated', $request->user(), $context->academy(), $sponsor, request: $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Sponsor updated.']);

        return back();
    }

    public function storeCommitment(Request $request, AcademyContext $context, AuditLogger $audit): RedirectResponse
    {
        $this->authorizeFinance($context);
        $academy = $context->academy();
        $forAcademy = fn (Builder $query): Builder => $query->where('academy_id', $academy->id);
        $data = $request->validate([
            'sponsor_id' => ['required', 'integer', Rule::exists('sponsors', 'id')->where($forAcademy)],
            'academy_cycle_id' => ['nullable', 'integer', Rule::exists('academy_cycles', 'id')->withoutTrashed()->where($forAcademy)],
            'name' => ['required', 'string', 'max:160'], 'amount' => ['required', 'regex:/^\d{1,12}(?:\.\d{1,2})?$/', 'not_in:0,0.00'],
            'agreement_reference' => ['nullable', 'string', 'max:160'], 'benefits' => ['nullable', 'string', 'max:2000'],
            'starts_on' => ['nullable', 'date'], 'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'renewal_on' => ['nullable', 'date'], 'status' => ['required', Rule::in(['active', 'completed', 'cancelled'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $commitment = SponsorCommitment::query()->create([
            ...$data, 'academy_id' => $academy->id, 'currency' => $academy->currency,
            'committed_minor' => Money::toMinor($data['amount']), 'created_by_user_id' => $request->user()->id,
        ]);
        $audit->record('academy.sponsor.commitment_created', $request->user(), $academy, $commitment, request: $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Sponsor commitment created.']);

        return back();
    }

    public function updateCommitment(Request $request, SponsorCommitment $commitment, AcademyContext $context, AuditLogger $audit): RedirectResponse
    {
        $this->authorizeFinance($context);
        abort_unless($commitment->academy_id === $context->id(), 404);
        $academy = $context->academy();
        $forAcademy = fn (Builder $query): Builder => $query->where('academy_id', $academy->id);
        $data = $request->validate([
            'sponsor_id' => ['required', 'integer', Rule::exists('sponsors', 'id')->where($forAcademy)],
            'academy_cycle_id' => ['nullable', 'integer', Rule::exists('academy_cycles', 'id')->withoutTrashed()->where($forAcademy)],
            'name' => ['required', 'string', 'max:160'], 'amount' => ['required', 'regex:/^\d{1,12}(?:\.\d{1,2})?$/', 'not_in:0,0.00'],
            'agreement_reference' => ['nullable', 'string', 'max:160'], 'benefits' => ['nullable', 'string', 'max:2000'],
            'starts_on' => ['nullable', 'date'], 'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'renewal_on' => ['nullable', 'date'], 'status' => ['required', Rule::in(['active', 'completed', 'cancelled'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $allocated = ScholarshipInvoiceAllocation::query()
            ->whereHas('award', fn ($query) => $query->where('sponsor_commitment_id', $commitment->id))->sum('amount_minor');
        $amount = Money::toMinor($data['amount']);
        if ($amount < max($commitment->received_minor, $allocated)) {
            throw ValidationException::withMessages(['amount' => 'The commitment cannot be reduced below its received or allocated amount.']);
        }
        $cycleId = filled($data['academy_cycle_id']) ? (int) $data['academy_cycle_id'] : null;
        if ($allocated > 0 && ((int) $data['sponsor_id'] !== $commitment->sponsor_id
            || $cycleId !== $commitment->academy_cycle_id)) {
            throw ValidationException::withMessages(['sponsor_id' => 'Sponsor and cycle cannot change after scholarship funds are allocated.']);
        }
        $commitment->update([...$data, 'committed_minor' => $amount]);
        $audit->record('academy.sponsor.commitment_updated', $request->user(), $academy, $commitment, request: $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Sponsor commitment updated.']);

        return back();
    }

    public function storeScheme(Request $request, AcademyContext $context, AuditLogger $audit): RedirectResponse
    {
        $this->authorizeFinance($context);
        $scheme = $this->saveScheme($request, $context);
        $audit->record('academy.scholarship.scheme_created', $request->user(), $context->academy(), $scheme, request: $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Scholarship scheme created.']);

        return back();
    }

    public function updateScheme(Request $request, ScholarshipScheme $scheme, AcademyContext $context, AuditLogger $audit): RedirectResponse
    {
        $this->authorizeFinance($context);
        abort_unless($scheme->academy_id === $context->id(), 404);
        $scheme = $this->saveScheme($request, $context, $scheme);
        $audit->record('academy.scholarship.scheme_updated', $request->user(), $context->academy(), $scheme, request: $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Scholarship scheme updated.']);

        return back();
    }

    public function storeAward(Request $request, AcademyContext $context, AuditLogger $audit): RedirectResponse
    {
        $this->authorizeFinance($context);
        $academy = $context->academy();
        $forAcademy = fn (Builder $query): Builder => $query->where('academy_id', $academy->id);
        $data = $request->validate([
            'scholarship_scheme_id' => ['required', 'integer', Rule::exists('scholarship_schemes', 'id')->where($forAcademy)],
            'player_id' => ['required', 'integer', Rule::exists('players', 'id')->withoutTrashed()->where($forAcademy)],
            'enrollment_id' => ['nullable', 'integer', Rule::exists('enrollments', 'id')->where($forAcademy)],
            'sponsor_commitment_id' => ['nullable', 'integer', Rule::exists('sponsor_commitments', 'id')->where($forAcademy)],
            'starts_on' => ['nullable', 'date'], 'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $scheme = $academy->scholarshipSchemes()->where('is_active', true)->findOrFail($data['scholarship_scheme_id']);
        if (isset($data['enrollment_id']) && ! $academy->enrollments()->whereKey($data['enrollment_id'])->where('player_id', $data['player_id'])->exists()) {
            throw ValidationException::withMessages(['enrollment_id' => 'The enrollment does not belong to the selected beneficiary.']);
        }
        if ($scheme->funding_type === 'sponsor') {
            $commitment = $academy->sponsorCommitments()->whereKey($data['sponsor_commitment_id'] ?? 0)->first();
            if ($commitment === null || $commitment->sponsor_id !== $scheme->sponsor_id || $commitment->status !== 'active') {
                throw ValidationException::withMessages(['sponsor_commitment_id' => 'Select an active commitment from the scheme sponsor.']);
            }
        } else {
            $data['sponsor_commitment_id'] = null;
        }
        $award = ScholarshipAward::query()->create([
            ...$data, 'academy_id' => $academy->id, 'status' => 'active', 'approved_at' => now(),
            'approved_by_user_id' => $request->user()->id,
        ]);
        $audit->record('academy.scholarship.award_created', $request->user(), $academy, $award, request: $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Scholarship awarded to beneficiary.']);

        return back();
    }

    public function applyAward(Request $request, ScholarshipAward $award, AcademyContext $context, ApplyScholarshipAward $apply): RedirectResponse
    {
        $this->authorizeFinance($context);
        $data = $request->validate(['invoice_id' => ['required', 'integer']]);
        $invoice = $context->academy()->invoices()->findOrFail($data['invoice_id']);
        $apply->handle($context->academy(), $award, $invoice, $request->user(), $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Scholarship applied to invoice.']);

        return back();
    }

    public function recordContribution(Request $request, ScholarshipInvoiceAllocation $allocation, AcademyContext $context, RecordSponsorContribution $record): RedirectResponse
    {
        $this->authorizeFinance($context);
        $data = $request->validate([
            'amount' => ['required', 'regex:/^\d{1,12}(?:\.\d{1,2})?$/', 'not_in:0,0.00'],
            'method' => ['required', Rule::enum(AcademyPaymentMethod::class), Rule::notIn([AcademyPaymentMethod::Paystack->value])],
            'received_on' => ['required', 'date', 'before_or_equal:today'],
            'external_reference' => ['nullable', 'string', 'max:120'], 'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $record->handle($context->academy(), $allocation, $request->user(), $data, $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Sponsor contribution recorded and allocated.']);

        return back();
    }

    private function saveScheme(Request $request, AcademyContext $context, ?ScholarshipScheme $scheme = null): ScholarshipScheme
    {
        $academy = $context->academy();
        $forAcademy = fn (Builder $query): Builder => $query->where('academy_id', $academy->id);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'], 'funding_type' => ['required', Rule::in(['academy', 'sponsor'])],
            'sponsor_id' => ['nullable', 'integer', Rule::exists('sponsors', 'id')->where($forAcademy)],
            'coverage_type' => ['required', Rule::in(['full', 'percentage', 'fixed'])],
            'percentage' => ['nullable', 'numeric', 'gt:0', 'lte:100'],
            'fixed_amount' => ['nullable', 'regex:/^\d{1,12}(?:\.\d{1,2})?$/', 'not_in:0,0.00'],
            'academy_cycle_id' => ['nullable', 'integer', Rule::exists('academy_cycles', 'id')->withoutTrashed()->where($forAcademy)],
            'academy_cycle_term_id' => ['nullable', 'integer', Rule::exists('academy_cycle_terms', 'id')->where($forAcademy)],
            'programme_id' => ['nullable', 'integer', Rule::exists('programmes', 'id')->withoutTrashed()->where($forAcademy)],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->withoutTrashed()->where($forAcademy)],
            'starts_on' => ['nullable', 'date'], 'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'description' => ['nullable', 'string', 'max:3000'], 'is_active' => ['required', 'boolean'],
        ]);
        if ($data['funding_type'] === 'sponsor' && empty($data['sponsor_id'])) {
            throw ValidationException::withMessages(['sponsor_id' => 'Select the sponsor funding this scheme.']);
        }
        if ($data['coverage_type'] === 'percentage' && empty($data['percentage'])) {
            throw ValidationException::withMessages(['percentage' => 'Enter the percentage covered.']);
        }
        if ($data['coverage_type'] === 'fixed' && empty($data['fixed_amount'])) {
            throw ValidationException::withMessages(['fixed_amount' => 'Enter the fixed amount covered.']);
        }
        if (! empty($data['academy_cycle_term_id'])) {
            $termMatches = $academy->cycleTerms()->whereKey($data['academy_cycle_term_id'])
                ->where('academy_cycle_id', $data['academy_cycle_id'])
                ->whereHas('cycle', fn ($cycle) => $cycle->where('type', AcademyCycleType::Fixed->value))
                ->exists();
            if (! $termMatches) {
                throw ValidationException::withMessages(['academy_cycle_term_id' => 'Select a term from a fixed cycle. Rolling cycles do not use terms.']);
            }
        }
        $values = [
            ...$data, 'academy_id' => $academy->id, 'currency' => $academy->currency,
            'sponsor_id' => $data['funding_type'] === 'sponsor' ? $data['sponsor_id'] : null,
            'percentage_basis_points' => $data['coverage_type'] === 'percentage' ? (int) round((float) $data['percentage'] * 100) : null,
            'fixed_amount_minor' => $data['coverage_type'] === 'fixed' ? Money::toMinor((string) $data['fixed_amount']) : null,
        ];
        unset($values['percentage'], $values['fixed_amount']);
        if ($scheme === null) {
            $values['created_by_user_id'] = $request->user()->id;

            return ScholarshipScheme::query()->create($values);
        }
        $scheme->update($values);

        return $scheme;
    }

    /** @return array<string, array<int, mixed>> */
    private function sponsorRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'], 'type' => ['required', Rule::in(['organisation', 'individual'])],
            'contact_name' => ['nullable', 'string', 'max:160'], 'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'], 'address' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'], 'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }

    private function authorizeFinance(AcademyContext $context): void
    {
        abort_unless($context->canManageFinance(), 403);
    }
}
