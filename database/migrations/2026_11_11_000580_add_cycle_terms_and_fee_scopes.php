<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academy_cycle_terms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academy_id');
            $table->foreignId('academy_cycle_id');
            $table->string('name', 120);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['academy_id', 'id']);
            $table->unique(['academy_id', 'academy_cycle_id', 'name'], 'cycle_term_name_unique');
            $table->index(['academy_id', 'academy_cycle_id', 'sort_order'], 'cycle_term_order_index');
            $table->foreign('academy_id')->references('id')->on('academies')->cascadeOnDelete();
            $table->foreign(['academy_id', 'academy_cycle_id'], 'cycle_term_cycle_fk')
                ->references(['academy_id', 'id'])->on('academy_cycles')->cascadeOnDelete();
        });

        Schema::table('programme_cycle_fee_items', function (Blueprint $table): void {
            $table->foreignId('academy_cycle_term_id')->nullable()->after('programme_cycle_id');
            $table->foreignId('cohort_id')->nullable()->after('academy_cycle_term_id');
            $table->index(['academy_id', 'academy_cycle_term_id', 'cohort_id'], 'programme_fee_scope_index');
            $table->foreign(['academy_id', 'academy_cycle_term_id'], 'programme_fee_term_fk')
                ->references(['academy_id', 'id'])->on('academy_cycle_terms')->restrictOnDelete();
            $table->foreign(['academy_id', 'cohort_id'], 'programme_fee_cohort_fk')
                ->references(['academy_id', 'id'])->on('cohorts')->restrictOnDelete();
        });

        Schema::table('academy_cycle_fees', function (Blueprint $table): void {
            $table->dropUnique(['academy_id', 'academy_cycle_id', 'name']);
            $table->foreignId('academy_cycle_term_id')->nullable()->after('academy_cycle_id');
            $table->foreignId('cohort_id')->nullable()->after('academy_cycle_term_id');
            $table->unsignedBigInteger('term_scope_id')->default(0)->after('cohort_id');
            $table->unsignedBigInteger('cohort_scope_id')->default(0)->after('term_scope_id');
            $table->unique(
                ['academy_id', 'academy_cycle_id', 'name', 'term_scope_id', 'cohort_scope_id'],
                'cycle_fee_name_scope_unique',
            );
            $table->index(['academy_id', 'academy_cycle_term_id', 'cohort_id'], 'cycle_fee_scope_index');
            $table->foreign(['academy_id', 'academy_cycle_term_id'], 'cycle_fee_term_fk')
                ->references(['academy_id', 'id'])->on('academy_cycle_terms')->restrictOnDelete();
            $table->foreign(['academy_id', 'cohort_id'], 'cycle_fee_cohort_fk')
                ->references(['academy_id', 'id'])->on('cohorts')->restrictOnDelete();
        });

        $now = now();
        DB::table('academy_cycles')->orderBy('id')->get()->each(function (object $cycle) use ($now): void {
            $termId = DB::table('academy_cycle_terms')->insertGetId([
                'academy_id' => $cycle->academy_id,
                'academy_cycle_id' => $cycle->id,
                'name' => 'Default term',
                'starts_on' => $cycle->starts_on,
                'ends_on' => $cycle->ends_on,
                'sort_order' => 1,
                'is_default' => true,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $offeringIds = DB::table('programme_cycles')->where('academy_cycle_id', $cycle->id)->pluck('id');
            DB::table('programme_cycle_fee_items')->whereIn('programme_cycle_id', $offeringIds)
                ->update(['academy_cycle_term_id' => $termId, 'updated_at' => $now]);
        });
    }

    public function down(): void
    {
        $hasCustomTerms = DB::table('academy_cycle_terms')->where('is_default', false)->exists();
        $hasScopedCycleFees = DB::table('academy_cycle_fees')
            ->where(fn ($query) => $query->whereNotNull('academy_cycle_term_id')->orWhereNotNull('cohort_id'))
            ->exists();
        $hasCohortProgrammeFees = DB::table('programme_cycle_fee_items')->whereNotNull('cohort_id')->exists();
        if ($hasCustomTerms || $hasScopedCycleFees || $hasCohortProgrammeFees) {
            throw new RuntimeException('Remove custom terms and term/cohort fee scopes before rolling back this migration.');
        }

        Schema::table('academy_cycle_fees', function (Blueprint $table): void {
            $table->dropForeign('cycle_fee_term_fk');
            $table->dropForeign('cycle_fee_cohort_fk');
            $table->dropIndex('cycle_fee_scope_index');
            $table->dropUnique('cycle_fee_name_scope_unique');
            $table->dropColumn(['academy_cycle_term_id', 'cohort_id', 'term_scope_id', 'cohort_scope_id']);
            $table->unique(['academy_id', 'academy_cycle_id', 'name']);
        });
        Schema::table('programme_cycle_fee_items', function (Blueprint $table): void {
            $table->dropForeign('programme_fee_term_fk');
            $table->dropForeign('programme_fee_cohort_fk');
            $table->dropIndex('programme_fee_scope_index');
            $table->dropColumn(['academy_cycle_term_id', 'cohort_id']);
        });
        Schema::dropIfExists('academy_cycle_terms');
    }
};
