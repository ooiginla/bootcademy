<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table): void {
            $table->string('uniqueness_key', 100)->nullable()->after('branch_id');
        });

        $seen = [];
        DB::table('enrollments')
            ->select(['id', 'academy_id', 'player_id', 'programme_id', 'academy_cycle_id', 'branch_id'])
            ->orderBy('id')
            ->cursor()
            ->each(function (object $enrollment) use (&$seen): void {
                $key = $this->key(
                    (int) $enrollment->player_id,
                    (int) $enrollment->programme_id,
                    $enrollment->academy_cycle_id === null ? null : (int) $enrollment->academy_cycle_id,
                    $enrollment->branch_id === null ? null : (int) $enrollment->branch_id,
                );
                $academyKey = "{$enrollment->academy_id}:{$key}";
                if (isset($seen[$academyKey])) {
                    return;
                }

                DB::table('enrollments')->where('id', $enrollment->id)->update(['uniqueness_key' => $key]);
                $seen[$academyKey] = true;
            });

        Schema::table('enrollments', function (Blueprint $table): void {
            $table->unique(['academy_id', 'uniqueness_key'], 'enrollment_membership_unique');
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table): void {
            $table->dropUnique('enrollment_membership_unique');
            $table->dropColumn('uniqueness_key');
        });
    }

    private function key(int $playerId, int $programmeId, ?int $cycleId, ?int $branchId): string
    {
        return implode(':', [$playerId, $programmeId, $cycleId ?? 0, $branchId ?? 0]);
    }
};
