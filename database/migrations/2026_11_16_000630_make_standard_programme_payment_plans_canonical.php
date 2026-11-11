<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programme_cycle_payment_plans', function (Blueprint $table): void {
            $table->unsignedSmallInteger('sort_order')->default(100)->after('description');
            $table->boolean('is_standard')->default(false)->after('is_legacy');
            $table->index(
                ['academy_id', 'programme_cycle_id', 'term_scope_id', 'cohort_scope_id', 'sort_order'],
                'programme_payment_plan_order_index',
            );
        });

        Schema::table('programme_cycle_payment_plan_items', function (Blueprint $table): void {
            $table->string('description', 500)->nullable()->after('name');
        });

        Schema::table('programme_cycle_fee_items', function (Blueprint $table): void {
            $table->string('description', 500)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('programme_cycle_fee_items', function (Blueprint $table): void {
            $table->dropColumn('description');
        });

        Schema::table('programme_cycle_payment_plan_items', function (Blueprint $table): void {
            $table->dropColumn('description');
        });

        Schema::table('programme_cycle_payment_plans', function (Blueprint $table): void {
            $table->dropIndex('programme_payment_plan_order_index');
            $table->dropColumn(['sort_order', 'is_standard']);
        });
    }
};
