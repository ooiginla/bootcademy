<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table): void {
            $table->foreignId('programme_cycle_payment_plan_id')->nullable()->after('programme_cycle_id');
            $table->foreign(
                ['academy_id', 'programme_cycle_payment_plan_id'],
                'application_payment_plan_fk',
            )->references(['academy_id', 'id'])->on('programme_cycle_payment_plans')->restrictOnDelete();
        });

        Schema::table('academy_invoices', function (Blueprint $table): void {
            $table->foreignId('programme_cycle_payment_plan_id')->nullable()->after('programme_cycle_id');
            $table->foreign(
                ['academy_id', 'programme_cycle_payment_plan_id'],
                'invoice_payment_plan_fk',
            )->references(['academy_id', 'id'])->on('programme_cycle_payment_plans')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('academy_invoices', function (Blueprint $table): void {
            $table->dropForeign('invoice_payment_plan_fk');
            $table->dropColumn('programme_cycle_payment_plan_id');
        });

        Schema::table('applications', function (Blueprint $table): void {
            $table->dropForeign('application_payment_plan_fk');
            $table->dropColumn('programme_cycle_payment_plan_id');
        });
    }
};
