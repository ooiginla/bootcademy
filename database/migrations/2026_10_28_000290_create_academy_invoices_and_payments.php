<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academy_invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academy_id');
            $table->string('number', 32);
            $table->foreignId('application_id')->nullable();
            $table->foreignId('player_id')->nullable();
            $table->foreignId('academy_cycle_id');
            $table->foreignId('programme_cycle_id');
            $table->foreignId('programme_id');
            $table->foreignId('branch_id')->nullable();
            $table->string('purpose', 24);
            $table->string('status', 24)->default('issued');
            $table->char('currency', 3);
            $table->unsignedBigInteger('total_minor');
            $table->unsignedBigInteger('paid_minor')->default(0);
            $table->timestamp('issued_at');
            $table->date('due_on')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['academy_id', 'number']);
            $table->unique(['academy_id', 'id']);
            $table->unique(['academy_id', 'application_id', 'purpose'], 'application_invoice_purpose_unique');
            $table->index(['academy_id', 'status', 'issued_at']);
            $table->index(['academy_id', 'player_id', 'academy_cycle_id']);
            $table->foreign('academy_id')->references('id')->on('academies')->cascadeOnDelete();
            $table->foreign(['academy_id', 'application_id'], 'invoice_application_fk')->references(['academy_id', 'id'])->on('applications')->restrictOnDelete();
            $table->foreign(['academy_id', 'player_id'], 'invoice_player_fk')->references(['academy_id', 'id'])->on('players')->restrictOnDelete();
            $table->foreign(['academy_id', 'academy_cycle_id'], 'invoice_cycle_fk')->references(['academy_id', 'id'])->on('academy_cycles')->restrictOnDelete();
            $table->foreign(['academy_id', 'programme_cycle_id'], 'invoice_programme_cycle_fk')->references(['academy_id', 'id'])->on('programme_cycles')->restrictOnDelete();
            $table->foreign(['academy_id', 'programme_id'], 'invoice_programme_fk')->references(['academy_id', 'id'])->on('programmes')->restrictOnDelete();
            $table->foreign(['academy_id', 'branch_id'], 'invoice_branch_fk')->references(['academy_id', 'id'])->on('branches')->restrictOnDelete();
        });

        Schema::create('academy_invoice_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academy_id');
            $table->foreignId('academy_invoice_id');
            $table->string('description', 160);
            $table->unsignedBigInteger('amount_minor');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('academy_id')->references('id')->on('academies')->cascadeOnDelete();
            $table->foreign(['academy_id', 'academy_invoice_id'], 'invoice_item_parent_fk')
                ->references(['academy_id', 'id'])->on('academy_invoices')->cascadeOnDelete();
        });

        Schema::create('academy_invoice_installments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academy_id');
            $table->foreignId('academy_invoice_id');
            $table->string('name', 120);
            $table->unsignedBigInteger('amount_minor');
            $table->unsignedBigInteger('paid_minor')->default(0);
            $table->date('due_on')->nullable();
            $table->unsignedSmallInteger('due_offset_days')->nullable();
            $table->unsignedSmallInteger('sequence');
            $table->timestamps();

            $table->unique(['academy_invoice_id', 'sequence']);
            $table->unique(['academy_id', 'id']);
            $table->foreign('academy_id')->references('id')->on('academies')->cascadeOnDelete();
            $table->foreign(['academy_id', 'academy_invoice_id'], 'invoice_installment_parent_fk')
                ->references(['academy_id', 'id'])->on('academy_invoices')->cascadeOnDelete();
        });

        Schema::create('academy_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academy_id')->constrained()->cascadeOnDelete();
            $table->string('reference', 64);
            $table->foreignId('application_id')->nullable();
            $table->foreignId('player_id')->nullable();
            $table->string('method', 32);
            $table->string('status', 24);
            $table->char('currency', 3);
            $table->unsignedBigInteger('amount_minor');
            $table->date('received_on');
            $table->string('external_reference', 120)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['academy_id', 'reference']);
            $table->unique(['academy_id', 'external_reference']);
            $table->unique(['academy_id', 'id']);
            $table->index(['academy_id', 'player_id', 'received_on']);
            $table->foreign(['academy_id', 'application_id'], 'payment_application_fk')->references(['academy_id', 'id'])->on('applications')->restrictOnDelete();
            $table->foreign(['academy_id', 'player_id'], 'payment_player_fk')->references(['academy_id', 'id'])->on('players')->restrictOnDelete();
        });

        Schema::create('academy_payment_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academy_id');
            $table->foreignId('academy_payment_id');
            $table->foreignId('academy_invoice_id');
            $table->foreignId('academy_invoice_installment_id')->nullable();
            $table->unsignedBigInteger('amount_minor');
            $table->timestamps();

            $table->foreign('academy_id')->references('id')->on('academies')->cascadeOnDelete();
            $table->foreign(['academy_id', 'academy_payment_id'], 'allocation_payment_fk')->references(['academy_id', 'id'])->on('academy_payments')->restrictOnDelete();
            $table->foreign(['academy_id', 'academy_invoice_id'], 'allocation_invoice_fk')->references(['academy_id', 'id'])->on('academy_invoices')->restrictOnDelete();
            $table->foreign(['academy_id', 'academy_invoice_installment_id'], 'allocation_installment_fk')->references(['academy_id', 'id'])->on('academy_invoice_installments')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academy_payment_allocations');
        Schema::dropIfExists('academy_payments');
        Schema::dropIfExists('academy_invoice_installments');
        Schema::dropIfExists('academy_invoice_items');
        Schema::dropIfExists('academy_invoices');
    }
};
