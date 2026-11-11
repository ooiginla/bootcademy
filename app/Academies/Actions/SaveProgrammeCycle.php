<?php

namespace App\Academies\Actions;

use App\Enums\AcademyCycleType;
use App\Enums\EnrollmentPaymentRule;
use App\Enums\ProgrammeCyclePaymentMode;
use App\Models\Academy;
use App\Models\AcademyCycle;
use App\Models\ProgrammeCycle;
use App\Models\User;
use App\Platform\AuditLogger;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SaveProgrammeCycle
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param array<string, mixed> $data */
    public function handle(
        Academy $academy,
        AcademyCycle $cycle,
        User $actor,
        array $data,
        Request $request,
        ?ProgrammeCycle $offering = null,
    ): ProgrammeCycle {
        abort_unless($cycle->academy_id === $academy->id, 404);

        return DB::transaction(function () use ($academy, $cycle, $actor, $data, $request, $offering): ProgrammeCycle {
            $offering ??= new ProgrammeCycle(['academy_id' => $academy->id, 'academy_cycle_id' => $cycle->id]);
            abort_unless($offering->academy_id === $academy->id && $offering->academy_cycle_id === $cycle->id, 404);
            $wasExisting = $offering->exists;
            if ($wasExisting) {
                $offering = ProgrammeCycle::query()->whereKey($offering->id)->lockForUpdate()->firstOrFail();
            }
            $offering->fill([
                'programme_id' => $data['programme_id'], 'branch_id' => $data['branch_id'] ?? null,
                'fee_name' => trim((string) $data['fee_name']),
                'branch_scope_id' => (int) ($data['branch_id'] ?? 0), 'currency' => $academy->currency,
                'application_fee_minor' => Money::toMinor((string) $data['application_fee']),
                'payment_mode' => ProgrammeCyclePaymentMode::OneOff,
                'enrollment_payment_rule' => $offering->exists
                    ? $offering->enrollment_payment_rule : EnrollmentPaymentRule::FullPayment,
                'is_active' => $data['is_active'],
            ]);
            $offering->save();
            $defaultTerm = null;
            if ($cycle->supportsTerms()) {
                $defaultTerm = $cycle->terms()->where('is_default', true)->first()
                    ?? $cycle->terms()->create([
                        'academy_id' => $academy->id, 'name' => 'Default term',
                        'starts_on' => $cycle->starts_on, 'ends_on' => $cycle->ends_on,
                        'sort_order' => 1, 'is_default' => true, 'is_active' => true,
                    ]);
            }
            $offering->feeItems()->delete();
            foreach (array_values($data['fee_items']) as $index => $item) {
                $offering->feeItems()->create([
                    'academy_id' => $academy->id, 'name' => trim((string) $item['name']),
                    'description' => filled($item['description'] ?? null) ? trim((string) $item['description']) : null,
                    'academy_cycle_term_id' => $cycle->supportsTerms()
                        ? (array_key_exists('academy_cycle_term_id', $item) ? $item['academy_cycle_term_id'] : $defaultTerm->id)
                        : null,
                    'cohort_id' => $item['cohort_id'] ?? null,
                    'amount_minor' => Money::toMinor((string) $item['amount']), 'sort_order' => $index,
                ]);
            }
            $offering->installments()->delete();

            $activeStandardScopes = [];
            $feeScopes = $offering->feeItems()->orderBy('sort_order')->get()
                ->groupBy(fn ($item): string => (int) ($item->academy_cycle_term_id ?? 0).':'.(int) ($item->cohort_id ?? 0));
            foreach ($feeScopes as $scopeKey => $items) {
                $first = $items->firstOrFail();
                $termId = $first->academy_cycle_term_id;
                $cohortId = $first->cohort_id;
                $activeStandardScopes[] = $scopeKey;
                $standard = $offering->paymentPlans()
                    ->where('term_scope_id', (int) ($termId ?? 0))
                    ->where('cohort_scope_id', (int) ($cohortId ?? 0))
                    ->where('is_standard', true)
                    ->lockForUpdate()
                    ->first();
                $standard ??= $offering->paymentPlans()->make([
                    'academy_id' => $academy->id,
                    'created_by_user_id' => $actor->id,
                    'name' => 'Standard payment',
                    'description' => 'Pay the standard programme fee in one payment.',
                    'sort_order' => 0,
                    'is_default' => ! $offering->paymentPlans()
                        ->where('term_scope_id', (int) ($termId ?? 0))
                        ->where('cohort_scope_id', (int) ($cohortId ?? 0))
                        ->where('is_default', true)
                        ->exists(),
                    'is_active' => $offering->is_active,
                    'is_legacy' => false,
                    'is_standard' => true,
                ]);
                $totalMinor = (int) $items->sum('amount_minor');
                $standard->fill([
                    'academy_cycle_term_id' => $termId,
                    'cohort_id' => $cohortId,
                    'term_scope_id' => (int) ($termId ?? 0),
                    'cohort_scope_id' => (int) ($cohortId ?? 0),
                    'currency' => $offering->currency,
                    'total_minor' => $totalMinor,
                    'is_legacy' => false,
                    'is_standard' => true,
                ])->save();
                $standard->items()->delete();
                foreach ($items->values() as $index => $item) {
                    $standard->items()->create([
                        'academy_id' => $academy->id,
                        'name' => $item->name,
                        'description' => $item->description,
                        'amount_minor' => $item->amount_minor,
                        'sort_order' => $index,
                    ]);
                }
                $standard->installments()->delete();
                $standard->installments()->create([
                    'academy_id' => $academy->id,
                    'name' => 'Full payment',
                    'amount_minor' => $totalMinor,
                    'due_on' => $cycle->type === AcademyCycleType::Fixed ? $cycle->starts_on : null,
                    'due_offset_days' => $cycle->type === AcademyCycleType::Rolling ? 0 : null,
                    'sequence' => 1,
                ]);
            }
            $offering->paymentPlans()->where('is_standard', true)->get()
                ->reject(fn ($plan): bool => in_array($plan->term_scope_id.':'.$plan->cohort_scope_id, $activeStandardScopes, true))
                ->each(fn ($plan) => $plan->update(['is_active' => false, 'is_default' => false]));
            $this->audit->record(
                $wasExisting ? 'academy.programme_cycle.updated' : 'academy.programme_cycle.created',
                $actor, $academy, $offering,
                [
                    'cycle_id' => $cycle->id, 'programme_id' => $offering->programme_id,
                    'branch_id' => $offering->branch_id,
                    'fee_total_minor' => $offering->feeItems()->sum('amount_minor'),
                    'application_fee_minor' => $offering->application_fee_minor,
                ], $request,
            );

            return $offering->load(['programme', 'branch', 'feeItems', 'installments']);
        });
    }
}
