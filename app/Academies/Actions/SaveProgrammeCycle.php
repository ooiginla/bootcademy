<?php

namespace App\Academies\Actions;

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
            $offering->fill([
                'programme_id' => $data['programme_id'], 'branch_id' => $data['branch_id'] ?? null,
                'fee_name' => trim((string) $data['fee_name']),
                'branch_scope_id' => (int) ($data['branch_id'] ?? 0), 'currency' => $academy->currency,
                'application_fee_minor' => Money::toMinor((string) $data['application_fee']),
                'payment_mode' => $data['payment_mode'],
                'enrollment_payment_rule' => $offering->exists
                    ? $offering->enrollment_payment_rule : EnrollmentPaymentRule::FullPayment,
                'is_active' => $data['is_active'],
            ]);
            $offering->save();
            $defaultTerm = $cycle->terms()->where('is_default', true)->first()
                ?? $cycle->terms()->create([
                    'academy_id' => $academy->id, 'name' => 'Default term',
                    'starts_on' => $cycle->starts_on, 'ends_on' => $cycle->ends_on,
                    'sort_order' => 1, 'is_default' => true, 'is_active' => true,
                ]);
            $offering->feeItems()->delete();
            foreach (array_values($data['fee_items']) as $index => $item) {
                $offering->feeItems()->create([
                    'academy_id' => $academy->id, 'name' => trim((string) $item['name']),
                    'academy_cycle_term_id' => array_key_exists('academy_cycle_term_id', $item)
                        ? $item['academy_cycle_term_id'] : $defaultTerm->id,
                    'cohort_id' => $item['cohort_id'] ?? null,
                    'amount_minor' => Money::toMinor((string) $item['amount']), 'sort_order' => $index,
                ]);
            }
            $offering->installments()->delete();
            if ($data['payment_mode'] === ProgrammeCyclePaymentMode::Installments->value) {
                foreach (array_values($data['installments']) as $index => $installment) {
                    $offering->installments()->create([
                        'academy_id' => $academy->id, 'name' => trim((string) $installment['name']),
                        'amount_minor' => Money::toMinor((string) $installment['amount']),
                        'due_on' => ($installment['due_on'] ?? null) ?: null,
                        'due_offset_days' => ($installment['due_offset_days'] ?? null) === null || ($installment['due_offset_days'] ?? '') === ''
                            ? null : (int) $installment['due_offset_days'],
                        'sequence' => $index + 1,
                    ]);
                }
            }
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
