<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academy_cycles', function (Blueprint $table): void {
            $table->unsignedSmallInteger('duration_value')->nullable()->after('duration_months');
            $table->string('duration_unit', 12)->nullable()->after('duration_value');
        });

        DB::table('academy_cycles')->where('type', 'rolling')->whereNotNull('duration_months')->update([
            'duration_value' => DB::raw('duration_months'),
            'duration_unit' => 'months',
        ]);
    }

    public function down(): void
    {
        Schema::table('academy_cycles', function (Blueprint $table): void {
            $table->dropColumn(['duration_value', 'duration_unit']);
        });
    }
};
