<?php

namespace App\Academies\Actions;

use App\Models\Academy;
use App\Models\AcademyCycle;
use App\Models\AcademyCycleFee;
use App\Models\User;
use App\Platform\AuditLogger;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SaveAcademyCycleFee
{
    public function __construct(
        private readonly IssuePlayerCycleFeeInvoice $issueInvoice,
        private readonly AuditLogger $audit,
    ) {}

    /** @param array<string, mixed> $data */
    public function handle(Academy $academy, AcademyCycle $cycle, User $actor, array $data, Request $request, ?AcademyCycleFee $fee = null): AcademyCycleFee
    {
        return DB::transaction(function () use ($academy, $cycle, $actor, $data, $request, $fee): AcademyCycleFee {
            $fee ??= new AcademyCycleFee(['academy_id' => $academy->id, 'academy_cycle_id' => $cycle->id]);
            abort_unless($cycle->academy_id === $academy->id && $fee->academy_id === $academy->id, 404);
            $fee->fill([
                'name' => trim((string) $data['name']), 'category' => trim((string) $data['category']),
                'academy_cycle_term_id' => $data['academy_cycle_term_id'] ?? null,
                'cohort_id' => $data['cohort_id'] ?? null,
                'term_scope_id' => (int) ($data['academy_cycle_term_id'] ?? 0),
                'cohort_scope_id' => (int) ($data['cohort_id'] ?? 0),
                'currency' => $academy->currency, 'amount_minor' => Money::toMinor((string) $data['amount']),
                'is_compulsory' => $data['is_compulsory'], 'applies_to_all_programmes' => $data['applies_to_all_programmes'],
                'is_active' => $data['is_active'], 'created_by_user_id' => $fee->created_by_user_id ?? $actor->id,
            ]);
            $fee->save();
            $fee->programmes()->sync($data['applies_to_all_programmes'] ? [] : collect($data['programme_ids'])->mapWithKeys(fn ($id) => [(int) $id => ['academy_id' => $academy->id]])->all());
            if ($fee->is_compulsory && $fee->is_active) {
                $enrollments = $academy->enrollments()
                    ->where('academy_cycle_id', $cycle->id)
                    ->where('status', 'active');
                if (! $fee->applies_to_all_programmes) {
                    $enrollments->whereIn('programme_id', $fee->programmes()->select('programmes.id'));
                }
                if ($fee->cohort_id !== null) {
                    $enrollments->whereHas('cohortMemberships', fn ($query) => $query->where('cohort_id', $fee->cohort_id)->where('status', 'active'));
                }
                $enrollments->with('player')->get()->unique('player_id')->each(
                    fn ($enrollment) => $this->issueInvoice->handle($academy, $enrollment->player, $fee, $actor, $request),
                );
            }
            $this->audit->record('academy.cycle_fee.saved', $actor, $academy, $fee, ['cycle_id' => $cycle->id], $request);

            return $fee->load('programmes');
        });
    }
}
