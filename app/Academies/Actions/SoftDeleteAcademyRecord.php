<?php

namespace App\Academies\Actions;

use App\Academies\Deletion\DeletionBlocker;
use App\Academies\Deletion\DeletionBlockers;
use App\Enums\MembershipStatus;
use App\Models\Academy;
use App\Models\AcademyMembership;
use App\Models\ProgrammeCyclePaymentPlan;
use App\Models\User;
use App\Platform\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Soft-deletes a tenant record once nothing belongs to it any more.
 *
 * Callers authorize the actor first. This action re-checks tenant ownership,
 * locks the row, refuses while dependents remain, removes parts owned by the
 * record and writes an audit entry.
 */
class SoftDeleteAcademyRecord
{
    public function __construct(
        private readonly DeletionBlockers $blockers,
        private readonly AuditLogger $audit,
        private readonly SyncProgrammeFeeItems $syncFeeItems,
    ) {}

    /** @param array<string, mixed> $snapshot what the audit log keeps about the deleted record */
    public function handle(
        Academy $academy,
        User $actor,
        Model $record,
        string $label,
        string $name,
        array $snapshot,
        Request $request,
    ): void {
        abort_unless((int) $record->getAttribute('academy_id') === $academy->id, 404);

        DB::transaction(function () use ($academy, $actor, $record, $label, $name, $snapshot, $request): void {
            $locked = $record->newQuery()->whereKey($record->getKey())->lockForUpdate()->first();
            abort_if($locked === null, 404);

            $blockers = $this->blockers->for($locked, $academy);
            if ($blockers !== []) {
                throw ValidationException::withMessages(['delete' => $this->message($label, $name, $blockers)]);
            }

            $this->deleteOwnedParts($locked);

            $this->audit->record(
                'academy.'.str_replace(' ', '-', $label).'.deleted',
                $actor,
                $academy,
                $locked,
                ['name' => $name, 'snapshot' => $snapshot, 'soft_deleted' => true],
                $request,
            );

            $locked->delete();

            if ($locked instanceof ProgrammeCyclePaymentPlan) {
                // Promotes another plan to default if needed and refreshes the fee's own items.
                $this->syncFeeItems->handle($locked->programmeCycle()->firstOrFail(), $locked->academy_cycle_term_id, $locked->cohort_id);
            }
        });
    }

    private function deleteOwnedParts(Model $record): void
    {
        if ($record instanceof AcademyMembership) {
            $record->coachProfile?->delete();
            // A restored membership must be re-accepted before it grants access.
            $record->forceFill(['status' => MembershipStatus::Suspended])->save();
        }
    }

    /** @param list<DeletionBlocker> $blockers */
    private function message(string $label, string $name, array $blockers): string
    {
        $removable = array_filter($blockers, fn (DeletionBlocker $blocker): bool => ! $blocker->permanent);
        $permanent = array_filter($blockers, fn (DeletionBlocker $blocker): bool => $blocker->permanent);
        $subject = ucfirst($label).' "'.$name.'"';

        $parts = [];
        if ($removable !== []) {
            $parts[] = $subject.' still has '.$this->list($removable).'. Delete or move those first.';
        }
        if ($permanent !== []) {
            $parts[] = ($removable === [] ? $subject.' has ' : 'It also has ')
                .$this->list($permanent).' that must be kept, so it cannot be deleted.';
        }

        return implode(' ', $parts);
    }

    /** @param array<int, DeletionBlocker> $blockers */
    private function list(array $blockers): string
    {
        $items = array_values(array_map(fn (DeletionBlocker $blocker): string => $blocker->describe(), $blockers));
        $last = array_pop($items);

        return $items === [] ? (string) $last : implode(', ', $items).' and '.$last;
    }
}
