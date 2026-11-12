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
            // Prices live on payment plans, added from the programme fee details page.
            // Existing plans and their fee items are left as they are.

            $this->audit->record(
                $wasExisting ? 'academy.programme_cycle.updated' : 'academy.programme_cycle.created',
                $actor, $academy, $offering,
                [
                    'cycle_id' => $cycle->id, 'programme_id' => $offering->programme_id,
                    'branch_id' => $offering->branch_id,
                    'application_fee_minor' => $offering->application_fee_minor,
                ], $request,
            );

            return $offering->load(['programme', 'branch', 'feeItems', 'installments']);
        });
    }
}
