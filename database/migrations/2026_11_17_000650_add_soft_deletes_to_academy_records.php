<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Soft deletes for tenant-managed records.
 *
 * Deleted rows stay in the database for history and audit. Each human-facing
 * unique key (names, codes, SKUs, references) is rebuilt to include the
 * generated `undeleted` column, which is 1 for live rows and NULL for deleted
 * rows. Unique indexes treat NULLs as distinct, so a deleted record no longer
 * reserves its name or code while live records stay unique.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $tables = [
        'applications',
        'branches',
        'programmes',
        'training_sessions',
        'academy_cycles',
        'academy_cycle_fees',
        'programme_cycles',
        'programme_cycle_payment_plans',
        'academy_expenses',
        'players',
        'academy_memberships',
        'academy_coach_profiles',
        'products',
    ];

    /** @var array<string, array<string, list<string>>> table => [old index name => columns] */
    private array $uniqueKeys = [
        'applications' => [
            'applications_academy_id_external_reference_unique' => ['academy_id', 'external_reference'],
            'applications_academy_id_internal_identifier_unique' => ['academy_id', 'internal_identifier'],
            'applications_academy_id_reference_unique' => ['academy_id', 'reference'],
        ],
        'branches' => [
            'branches_academy_id_code_unique' => ['academy_id', 'code'],
            'branches_academy_id_name_unique' => ['academy_id', 'name'],
        ],
        'programmes' => [
            'programmes_academy_id_code_unique' => ['academy_id', 'code'],
            'programmes_academy_id_name_unique' => ['academy_id', 'name'],
        ],
        'academy_cycles' => [
            'academy_cycles_academy_id_name_unique' => ['academy_id', 'name'],
        ],
        'academy_cycle_fees' => [
            'cycle_fee_name_scope_unique' => ['academy_id', 'academy_cycle_id', 'name', 'term_scope_id', 'cohort_scope_id'],
        ],
        'programme_cycles' => [
            'programme_cycle_scope_unique' => ['academy_id', 'academy_cycle_id', 'programme_id', 'branch_scope_id'],
        ],
        'programme_cycle_payment_plans' => [
            'programme_payment_plan_name_unique' => ['academy_id', 'programme_cycle_id', 'term_scope_id', 'cohort_scope_id', 'name'],
        ],
        'players' => [
            'players_academy_id_internal_identifier_unique' => ['academy_id', 'internal_identifier'],
        ],
        'products' => [
            'products_academy_id_sku_unique' => ['academy_id', 'sku'],
            'products_academy_id_slug_unique' => ['academy_id', 'slug'],
        ],
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                $blueprint->softDeletes();
                $blueprint->index(['academy_id', 'deleted_at'], $table.'_academy_deleted_index');
            });
        }

        foreach ($this->uniqueKeys as $table => $keys) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->unsignedTinyInteger('undeleted')
                    ->nullable()
                    ->virtualAs('case when deleted_at is null then 1 else null end');
            });

            // Add the replacement first so MySQL always has an index covering
            // the academy_id foreign key, then drop the original.
            Schema::table($table, function (Blueprint $blueprint) use ($keys): void {
                foreach ($keys as $name => $columns) {
                    $blueprint->unique([...$columns, 'undeleted'], $this->liveName($name));
                }
            });

            Schema::table($table, function (Blueprint $blueprint) use ($keys): void {
                foreach (array_keys($keys) as $name) {
                    $blueprint->dropUnique($name);
                }
            });
        }
    }

    /**
     * Rolling back fails if a live record now shares a key with a deleted
     * one; resolve those duplicates before rolling back.
     */
    public function down(): void
    {
        foreach ($this->uniqueKeys as $table => $keys) {
            Schema::table($table, function (Blueprint $blueprint) use ($keys): void {
                foreach ($keys as $name => $columns) {
                    $blueprint->unique($columns, $name);
                }
            });

            Schema::table($table, function (Blueprint $blueprint) use ($keys): void {
                foreach (array_keys($keys) as $name) {
                    $blueprint->dropUnique($this->liveName($name));
                }
                $blueprint->dropColumn('undeleted');
            });
        }

        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                $blueprint->dropIndex($table.'_academy_deleted_index');
                $blueprint->dropSoftDeletes();
            });
        }
    }

    private function liveName(string $name): string
    {
        return substr(str_replace('_unique', '', $name), 0, 52).'_live_unique';
    }
};
