<?php

namespace App\Academies\Actions;

use App\Models\Academy;
use App\Models\TrainingSession;
use App\Models\User;
use App\Platform\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SaveTrainingSession
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param array<string, mixed> $data */
    public function handle(Academy $academy, User $actor, array $data, Request $request, ?TrainingSession $session = null): TrainingSession
    {
        $session ??= new TrainingSession(['academy_id' => $academy->id, 'created_by_user_id' => $actor->id]);
        abort_unless($session->academy_id === $academy->id, 404);
        DB::transaction(function () use ($academy, $actor, $data, $request, $session): void {
            $created = ! $session->exists;
            $branchIds = array_map('intval', array_values($data['branch_ids']));
            $coachIds = array_map('intval', array_values($data['coach_membership_ids']));
            if ((bool) $data['all_branches']) {
                $branchIds = $academy->branches()->where('is_active', true)->pluck('id')->map(fn ($id): int => (int) $id)->all();
            }
            abort_if($branchIds === [], 422, 'Select at least one branch.');
            $data['branch_id'] = $branchIds[0];
            $data['coach_membership_id'] = $coachIds[0] ?? null;
            $data['starts_at'] = Carbon::createFromFormat('Y-m-d\TH:i', (string) $data['starts_at'], $academy->timezone)->utc();
            $data['ends_at'] = Carbon::createFromFormat('Y-m-d\TH:i', (string) $data['ends_at'], $academy->timezone)->utc();
            $session->fill(Arr::except($data, ['branch_ids', 'coach_membership_ids']))->save();
            $session->branches()->sync(collect($branchIds)->mapWithKeys(fn (int $id): array => [$id => ['academy_id' => $academy->id]])->all());
            $session->coaches()->sync(collect($coachIds)->mapWithKeys(fn (int $id): array => [$id => ['academy_id' => $academy->id]])->all());
            $this->audit->record($created ? 'academy.session.created' : 'academy.session.updated', $actor, $academy, $session, ['changed_fields' => array_keys($session->getChanges()), 'branch_ids' => $branchIds, 'coach_membership_ids' => $coachIds], $request);
        });

        return $session;
    }
}
