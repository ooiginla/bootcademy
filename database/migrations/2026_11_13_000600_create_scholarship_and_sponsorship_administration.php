<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sponsors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academy_id');
            $table->string('name', 160);
            $table->string('type', 24)->default('organisation');
            $table->string('contact_name', 160)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 24)->default('active');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['academy_id', 'id']);
            $table->index(['academy_id', 'status', 'name']);
            $table->foreign('academy_id')->references('id')->on('academies')->cascadeOnDelete();
        });

        Schema::create('sponsor_commitments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academy_id');
            $table->foreignId('sponsor_id');
            $table->foreignId('academy_cycle_id')->nullable();
            $table->string('name', 160);
            $table->string('agreement_reference', 160)->nullable();
            $table->text('benefits')->nullable();
            $table->char('currency', 3);
            $table->unsignedBigInteger('committed_minor');
            $table->unsignedBigInteger('received_minor')->default(0);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->date('renewal_on')->nullable();
            $table->string('status', 24)->default('active');
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['academy_id', 'id']);
            $table->index(['academy_id', 'status', 'ends_on']);
            $table->foreign('academy_id')->references('id')->on('academies')->cascadeOnDelete();
            $table->foreign(['academy_id', 'sponsor_id'], 'sponsor_commitment_sponsor_fk')
                ->references(['academy_id', 'id'])->on('sponsors')->restrictOnDelete();
            $table->foreign(['academy_id', 'academy_cycle_id'], 'sponsor_commitment_cycle_fk')
                ->references(['academy_id', 'id'])->on('academy_cycles')->restrictOnDelete();
        });

        Schema::create('scholarship_schemes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academy_id');
            $table->foreignId('sponsor_id')->nullable();
            $table->foreignId('academy_cycle_id')->nullable();
            $table->foreignId('academy_cycle_term_id')->nullable();
            $table->foreignId('programme_id')->nullable();
            $table->foreignId('branch_id')->nullable();
            $table->string('name', 160);
            $table->string('funding_type', 24);
            $table->string('coverage_type', 24);
            $table->unsignedSmallInteger('percentage_basis_points')->nullable();
            $table->unsignedBigInteger('fixed_amount_minor')->nullable();
            $table->char('currency', 3);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['academy_id', 'id']);
            $table->index(['academy_id', 'is_active', 'funding_type']);
            $table->foreign('academy_id')->references('id')->on('academies')->cascadeOnDelete();
            $table->foreign(['academy_id', 'sponsor_id'], 'scholarship_scheme_sponsor_fk')->references(['academy_id', 'id'])->on('sponsors')->restrictOnDelete();
            $table->foreign(['academy_id', 'academy_cycle_id'], 'scholarship_scheme_cycle_fk')->references(['academy_id', 'id'])->on('academy_cycles')->restrictOnDelete();
            $table->foreign(['academy_id', 'academy_cycle_term_id'], 'scholarship_scheme_term_fk')->references(['academy_id', 'id'])->on('academy_cycle_terms')->restrictOnDelete();
            $table->foreign(['academy_id', 'programme_id'], 'scholarship_scheme_programme_fk')->references(['academy_id', 'id'])->on('programmes')->restrictOnDelete();
            $table->foreign(['academy_id', 'branch_id'], 'scholarship_scheme_branch_fk')->references(['academy_id', 'id'])->on('branches')->restrictOnDelete();
        });

        Schema::create('scholarship_awards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academy_id');
            $table->foreignId('scholarship_scheme_id');
            $table->foreignId('player_id');
            $table->foreignId('enrollment_id')->nullable();
            $table->foreignId('sponsor_commitment_id')->nullable();
            $table->string('status', 24)->default('active');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('approved_at');
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['academy_id', 'id']);
            $table->index(['academy_id', 'player_id', 'status']);
            $table->foreign('academy_id')->references('id')->on('academies')->cascadeOnDelete();
            $table->foreign(['academy_id', 'scholarship_scheme_id'], 'scholarship_award_scheme_fk')->references(['academy_id', 'id'])->on('scholarship_schemes')->restrictOnDelete();
            $table->foreign(['academy_id', 'player_id'], 'scholarship_award_player_fk')->references(['academy_id', 'id'])->on('players')->restrictOnDelete();
            $table->foreign(['academy_id', 'enrollment_id'], 'scholarship_award_enrollment_fk')->references(['academy_id', 'id'])->on('enrollments')->restrictOnDelete();
            $table->foreign(['academy_id', 'sponsor_commitment_id'], 'scholarship_award_commitment_fk')->references(['academy_id', 'id'])->on('sponsor_commitments')->restrictOnDelete();
        });

        Schema::create('scholarship_invoice_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academy_id');
            $table->foreignId('scholarship_award_id');
            $table->foreignId('academy_invoice_id');
            $table->string('funding_type', 24);
            $table->unsignedBigInteger('amount_minor');
            $table->unsignedBigInteger('funded_minor')->default(0);
            $table->string('status', 24)->default('active');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['academy_id', 'id']);
            $table->unique(['scholarship_award_id', 'academy_invoice_id'], 'scholarship_award_invoice_unique');
            $table->index(['academy_id', 'funding_type', 'status']);
            $table->foreign('academy_id')->references('id')->on('academies')->cascadeOnDelete();
            $table->foreign(['academy_id', 'scholarship_award_id'], 'scholarship_allocation_award_fk')->references(['academy_id', 'id'])->on('scholarship_awards')->restrictOnDelete();
            $table->foreign(['academy_id', 'academy_invoice_id'], 'scholarship_allocation_invoice_fk')->references(['academy_id', 'id'])->on('academy_invoices')->restrictOnDelete();
        });

        Schema::table('academy_invoices', function (Blueprint $table): void {
            $table->unsignedBigInteger('scholarship_concession_minor')->default(0)->after('written_off_minor');
            $table->unsignedBigInteger('sponsor_responsibility_minor')->default(0)->after('scholarship_concession_minor');
            $table->unsignedBigInteger('sponsor_paid_minor')->default(0)->after('sponsor_responsibility_minor');
        });
        Schema::table('academy_invoice_installments', function (Blueprint $table): void {
            $table->unsignedBigInteger('scholarship_concession_minor')->default(0)->after('written_off_minor');
            $table->unsignedBigInteger('sponsor_responsibility_minor')->default(0)->after('scholarship_concession_minor');
            $table->unsignedBigInteger('sponsor_paid_minor')->default(0)->after('sponsor_responsibility_minor');
        });
        Schema::table('academy_payments', function (Blueprint $table): void {
            $table->foreignId('sponsor_id')->nullable()->after('player_id');
            $table->foreign(['academy_id', 'sponsor_id'], 'academy_payment_sponsor_fk')->references(['academy_id', 'id'])->on('sponsors')->restrictOnDelete();
        });
        Schema::table('academy_payment_allocations', function (Blueprint $table): void {
            $table->foreignId('scholarship_invoice_allocation_id')->nullable()->after('academy_invoice_installment_id');
            $table->foreign(['academy_id', 'scholarship_invoice_allocation_id'], 'payment_scholarship_allocation_fk')->references(['academy_id', 'id'])->on('scholarship_invoice_allocations')->restrictOnDelete();
        });

        Schema::create('sponsor_contributions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academy_id');
            $table->foreignId('sponsor_commitment_id');
            $table->foreignId('scholarship_invoice_allocation_id');
            $table->foreignId('academy_payment_id');
            $table->char('currency', 3);
            $table->unsignedBigInteger('amount_minor');
            $table->unsignedBigInteger('reversed_minor')->default(0);
            $table->date('received_on');
            $table->string('external_reference', 120)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['academy_id', 'id']);
            $table->unique('academy_payment_id');
            $table->index(['academy_id', 'received_on']);
            $table->foreign('academy_id')->references('id')->on('academies')->cascadeOnDelete();
            $table->foreign(['academy_id', 'sponsor_commitment_id'], 'sponsor_contribution_commitment_fk')->references(['academy_id', 'id'])->on('sponsor_commitments')->restrictOnDelete();
            $table->foreign(['academy_id', 'scholarship_invoice_allocation_id'], 'sponsor_contribution_allocation_fk')->references(['academy_id', 'id'])->on('scholarship_invoice_allocations')->restrictOnDelete();
            $table->foreign(['academy_id', 'academy_payment_id'], 'sponsor_contribution_payment_fk')->references(['academy_id', 'id'])->on('academy_payments')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('sponsors') && DB::table('sponsors')->exists()) {
            throw new RuntimeException('Archive scholarship and sponsorship records before rolling back this migration.');
        }
        Schema::dropIfExists('sponsor_contributions');
        Schema::table('academy_payment_allocations', function (Blueprint $table): void {
            $table->dropForeign('payment_scholarship_allocation_fk');
            $table->dropColumn('scholarship_invoice_allocation_id');
        });
        Schema::table('academy_payments', function (Blueprint $table): void {
            $table->dropForeign('academy_payment_sponsor_fk');
            $table->dropColumn('sponsor_id');
        });
        Schema::table('academy_invoice_installments', fn (Blueprint $table) => $table->dropColumn(['scholarship_concession_minor', 'sponsor_responsibility_minor', 'sponsor_paid_minor']));
        Schema::table('academy_invoices', fn (Blueprint $table) => $table->dropColumn(['scholarship_concession_minor', 'sponsor_responsibility_minor', 'sponsor_paid_minor']));
        Schema::dropIfExists('scholarship_invoice_allocations');
        Schema::dropIfExists('scholarship_awards');
        Schema::dropIfExists('scholarship_schemes');
        Schema::dropIfExists('sponsor_commitments');
        Schema::dropIfExists('sponsors');
    }
};
