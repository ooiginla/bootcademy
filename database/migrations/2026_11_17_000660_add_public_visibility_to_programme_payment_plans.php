<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programme_cycle_payment_plans', function (Blueprint $table): void {
            // Existing plans stay visible, matching how they behaved before this flag existed.
            $table->boolean('is_public')->default(true)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('programme_cycle_payment_plans', function (Blueprint $table): void {
            $table->dropColumn('is_public');
        });
    }
};
