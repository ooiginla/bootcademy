<?php

namespace App\Platform\Actions;

use App\Models\Academy;
use App\Models\AcademyMembership;
use App\Models\User;
use App\Platform\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Permanently removes an academy and everything it owns. Intended for spam or
 * test signups; it is the one deletion in the platform that is not soft.
 *
 * Every table with an `academy_id` column is cleared in foreign-key order
 * (children before parents), worked out from the live schema so new tables
 * are covered automatically. User accounts that belonged only to this academy
 * are removed too. A platform audit entry survives the purge.
 */
class PurgeAcademy
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(Academy $academy, User $actor, string $confirmation, Request $request): void
    {
        abort_unless($actor->is_platform_super_admin, 403);

        if (trim($confirmation) !== $academy->name) {
            throw ValidationException::withMessages(['confirmation' => 'Type the academy name exactly to confirm.']);
        }

        $academyId = $academy->id;
        $name = $academy->name;

        $summary = DB::transaction(function () use ($academy, $actor): array {
            $locked = Academy::query()->whereKey($academy->id)->lockForUpdate()->firstOrFail();
            $userIds = $this->exclusiveUserIds($locked, $actor);

            $rows = [];
            foreach ($this->deletionOrder() as $table) {
                $deleted = DB::table($table)->where('academy_id', $locked->id)->delete();
                if ($deleted > 0) {
                    $rows[$table] = $deleted;
                }
            }

            $locked->delete();
            $users = $this->deleteUsers($userIds, $actor);

            return ['rows' => $rows, 'users_deleted' => $users];
        });

        foreach (["academies/{$academyId}", "imports/{$academyId}"] as $directory) {
            Storage::disk('local')->deleteDirectory($directory);
        }

        $this->audit->record('platform.academy.purged', $actor, null, null, [
            'academy_id' => $academyId,
            'academy_name' => $name,
            ...$summary,
        ], $request);
    }

    /**
     * Members (and the creator) who have no other academy, are not platform
     * staff and are not the person purging. Collected before memberships go.
     *
     * @return list<int>
     */
    private function exclusiveUserIds(Academy $academy, User $actor): array
    {
        $candidates = AcademyMembership::withTrashed()
            ->where('academy_id', $academy->id)
            ->pluck('user_id')
            ->push($academy->created_by_user_id)
            ->unique();

        return array_values(User::query()
            ->whereIn('id', $candidates)
            ->where('id', '<>', $actor->id)
            ->where('is_platform_super_admin', false)
            ->whereDoesntHave('academyMemberships', fn ($query) => $query->withTrashed()->where('academy_id', '<>', $academy->id))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all());
    }

    /** @param list<int> $userIds */
    private function deleteUsers(array $userIds, User $actor): int
    {
        if ($userIds === []) {
            return 0;
        }

        // Keep anyone still referenced by another academy's records.
        return User::query()
            ->whereIn('id', $userIds)
            ->where('id', '<>', $actor->id)
            ->whereNotExists(fn ($query) => $query->from('academies')->whereColumn('academies.created_by_user_id', 'users.id'))
            ->whereNotExists(fn ($query) => $query->from('academy_invitations')->whereColumn('academy_invitations.invited_by_user_id', 'users.id'))
            ->delete();
    }

    /**
     * Tables owned by an academy, ordered so a table is cleared before any
     * table it references.
     *
     * @return list<string>
     */
    private function deletionOrder(): array
    {
        $tables = array_values(array_filter(
            array_map(fn (string $table): string => preg_replace('/^[^.]+\./', '', $table) ?? $table, Schema::getTableListing()),
            fn (string $table): bool => $table !== 'academies' && Schema::hasColumn($table, 'academy_id'),
        ));

        /** @var array<string, list<string>> $parents table => owned tables it references */
        $parents = [];
        foreach ($tables as $table) {
            $parents[$table] = [];
            foreach (Schema::getForeignKeys($table) as $foreignKey) {
                $parent = $foreignKey['foreign_table'];
                if ($parent !== $table && in_array($parent, $tables, true)) {
                    $parents[$table][] = $parent;
                }
            }
        }

        // Repeatedly take tables nothing remaining still references.
        $order = [];
        $remaining = $tables;
        while ($remaining !== []) {
            $referenced = [];
            foreach ($remaining as $table) {
                foreach ($parents[$table] as $parent) {
                    $referenced[$parent] = true;
                }
            }
            $leaves = array_values(array_filter($remaining, fn (string $table): bool => ! isset($referenced[$table])));
            if ($leaves === []) {
                throw new RuntimeException('Academy tables reference each other in a cycle: '.implode(', ', $remaining));
            }
            $order = [...$order, ...$leaves];
            $remaining = array_values(array_diff($remaining, $leaves));
        }

        return $order;
    }
}
