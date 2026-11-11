<?php

namespace App\Academies\Actions;

use App\Models\Academy;
use App\Models\ProgrammeCycle;
use App\Models\ProgrammeCyclePaymentPlan;
use App\Models\User;
use App\Platform\AuditLogger;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SaveProgrammeCyclePaymentPlan
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param array<string, mixed> $data */
    public function handle(
        Academy $academy,
        ProgrammeCycle $offering,
        User $actor,
        array $data,
        Request $request,
        ?ProgrammeCyclePaymentPlan $plan = null,
    ): ProgrammeCyclePaymentPlan {
        abort_unless($offering->academy_id === $academy->id, 404);

        return DB::transaction(function () use ($academy, $offering, $actor, $data, $request, $plan): ProgrammeCyclePaymentPlan {
            ProgrammeCycle::query()->whereKey($offering->id)->lockForUpdate()->firstOrFail();
            $plan ??= new ProgrammeCyclePaymentPlan([
                'academy_id' => $academy->id,
                'programme_cycle_id' => $offering->id,
                'created_by_user_id' => $actor->id,
            ]);
            abort_unless($plan->academy_id === $academy->id && $plan->programme_cycle_id === $offering->id, 404);
            $wasExisting = $plan->exists;
            $termId = $data['academy_cycle_term_id'] ?? null;
            $cohortId = $data['cohort_id'] ?? null;
            $hasDefault = $offering->paymentPlans()
                ->where('term_scope_id', (int) ($termId ?? 0))
                ->where('cohort_scope_id', (int) ($cohortId ?? 0))
                ->where('id', '<>', (int) $plan->id)
                ->where('is_default', true)
                ->exists();
            $isDefault = (bool) $data['is_default'] || ! $hasDefault;
            if ($isDefault) {
                $offering->paymentPlans()
                    ->where('term_scope_id', (int) ($termId ?? 0))
                    ->where('cohort_scope_id', (int) ($cohortId ?? 0))
                    ->where('id', '<>', (int) $plan->id)
                    ->update(['is_default' => false, 'updated_at' => now()]);
            }

            $feeItems = is_array($data['fee_items'] ?? null) ? $data['fee_items'] : [];
            $totalMinor = 0;
            foreach ($feeItems as $item) {
                if (is_array($item) && isset($item['amount'])) {
                    $totalMinor += Money::toMinor((string) $item['amount']);
                }
            }
            $plan->fill([
                'academy_cycle_term_id' => $termId,
                'cohort_id' => $cohortId,
                'term_scope_id' => (int) ($termId ?? 0),
                'cohort_scope_id' => (int) ($cohortId ?? 0),
                'name' => trim((string) $data['name']),
                'description' => $data['description'] ?: null,
                'sort_order' => (int) $data['sort_order'],
                'currency' => $offering->currency,
                'total_minor' => $totalMinor,
                'is_default' => $isDefault,
                'is_active' => (bool) $data['is_active'],
                'is_legacy' => false,
            ])->save();

            $plan->items()->delete();
            foreach (array_values($feeItems) as $index => $item) {
                if (! is_array($item)) {
                    continue;
                }

                $plan->items()->create([
                    'academy_id' => $academy->id,
                    'name' => trim((string) $item['name']),
                    'description' => filled($item['description'] ?? null) ? trim((string) $item['description']) : null,
                    'amount_minor' => Money::toMinor((string) $item['amount']),
                    'sort_order' => $index,
                ]);
            }
            $plan->installments()->delete();
            foreach (array_values($data['installments']) as $index => $installment) {
                $plan->installments()->create([
                    'academy_id' => $academy->id,
                    'name' => trim((string) $installment['name']),
                    'amount_minor' => Money::toMinor((string) $installment['amount']),
                    'due_on' => ($installment['due_on'] ?? null) ?: null,
                    'due_offset_days' => ($installment['due_offset_days'] ?? null) === null || ($installment['due_offset_days'] ?? '') === ''
                        ? null : (int) $installment['due_offset_days'],
                    'sequence' => $index + 1,
                ]);
            }

            if ($plan->is_standard) {
                $offering->feeItems()
                    ->where('academy_cycle_term_id', $termId)
                    ->where('cohort_id', $cohortId)
                    ->delete();
                foreach (array_values($feeItems) as $index => $item) {
                    if (! is_array($item)) {
                        continue;
                    }
                    $offering->feeItems()->create([
                        'academy_id' => $academy->id,
                        'academy_cycle_term_id' => $termId,
                        'cohort_id' => $cohortId,
                        'name' => trim((string) $item['name']),
                        'description' => filled($item['description'] ?? null) ? trim((string) $item['description']) : null,
                        'amount_minor' => Money::toMinor((string) $item['amount']),
                        'sort_order' => $index,
                    ]);
                }
            }

            $this->audit->record(
                $wasExisting ? 'academy.programme_payment_plan.updated' : 'academy.programme_payment_plan.created',
                $actor,
                $academy,
                $plan,
                [
                    'programme_cycle_id' => $offering->id,
                    'academy_cycle_term_id' => $termId,
                    'cohort_id' => $cohortId,
                    'total_minor' => $totalMinor,
                    'payment_count' => count($data['installments']),
                    'sort_order' => (int) $data['sort_order'],
                ],
                $request,
            );

            return $plan->load(['items', 'installments']);
        });
    }
}
