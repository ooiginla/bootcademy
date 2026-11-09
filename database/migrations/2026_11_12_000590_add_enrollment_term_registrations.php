<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollment_term_registrations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academy_id');
            $table->foreignId('enrollment_id');
            $table->foreignId('academy_cycle_term_id');
            $table->foreignId('cohort_id')->nullable();
            $table->timestamp('registered_at');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['academy_id', 'id']);
            $table->unique(['enrollment_id', 'academy_cycle_term_id'], 'enrollment_term_unique');
            $table->index(['academy_id', 'academy_cycle_term_id', 'registered_at'], 'enrollment_term_lookup');
            $table->foreign('academy_id')->references('id')->on('academies')->cascadeOnDelete();
            $table->foreign(['academy_id', 'enrollment_id'], 'enrollment_term_enrollment_fk')
                ->references(['academy_id', 'id'])->on('enrollments')->restrictOnDelete();
            $table->foreign(['academy_id', 'academy_cycle_term_id'], 'enrollment_term_term_fk')
                ->references(['academy_id', 'id'])->on('academy_cycle_terms')->restrictOnDelete();
            $table->foreign(['academy_id', 'cohort_id'], 'enrollment_term_cohort_fk')
                ->references(['academy_id', 'id'])->on('cohorts')->restrictOnDelete();
        });

        Schema::table('academy_invoices', function (Blueprint $table): void {
            $table->foreignId('enrollment_term_registration_id')->nullable()->after('academy_cycle_fee_id');
            $table->unique(['academy_id', 'enrollment_term_registration_id'], 'enrollment_term_invoice_unique');
            $table->foreign(['academy_id', 'enrollment_term_registration_id'], 'invoice_enrollment_term_fk')
                ->references(['academy_id', 'id'])->on('enrollment_term_registrations')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('academy_invoices', function (Blueprint $table): void {
            $table->dropForeign('invoice_enrollment_term_fk');
            $table->dropUnique('enrollment_term_invoice_unique');
            $table->dropColumn('enrollment_term_registration_id');
        });
        Schema::dropIfExists('enrollment_term_registrations');
    }
};
