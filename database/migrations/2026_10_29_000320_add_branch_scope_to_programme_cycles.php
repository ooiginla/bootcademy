<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasIndex('branches', 'branches_academy_id_unique')) {
            Schema::table('branches', function (Blueprint $table): void {
                $table->unique(['academy_id', 'id'], 'branches_academy_id_unique');
            });
        }
        if (! Schema::hasIndex('programme_cycles', 'programme_cycle_cycle_index')) {
            Schema::table('programme_cycles', function (Blueprint $table): void {
                $table->index(['academy_id', 'academy_cycle_id'], 'programme_cycle_cycle_index');
            });
        }
        Schema::table('programme_cycles', function (Blueprint $table): void {
            $table->dropUnique('programme_cycle_unique');
            $table->foreignId('branch_id')->nullable()->after('programme_id');
            $table->unsignedBigInteger('branch_scope_id')->default(0)->after('branch_id');
            $table->unique(
                ['academy_id', 'academy_cycle_id', 'programme_id', 'branch_scope_id'],
                'programme_cycle_scope_unique',
            );
            $table->index(['academy_id', 'branch_id'], 'programme_cycle_branch_index');
            $table->foreign(['academy_id', 'branch_id'], 'programme_cycle_branch_fk')
                ->references(['academy_id', 'id'])->on('branches')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('programme_cycles', function (Blueprint $table): void {
            $table->dropUnique('programme_cycle_scope_unique');
            $table->dropIndex('programme_cycle_branch_index');
            $table->dropForeign('programme_cycle_branch_fk');
            $table->dropColumn(['branch_id', 'branch_scope_id']);
            $table->unique(['academy_id', 'academy_cycle_id', 'programme_id'], 'programme_cycle_unique');
        });
        Schema::table('programme_cycles', function (Blueprint $table): void {
            $table->dropIndex('programme_cycle_cycle_index');
        });
        Schema::table('branches', function (Blueprint $table): void {
            $table->dropUnique('branches_academy_id_unique');
        });
    }
};
