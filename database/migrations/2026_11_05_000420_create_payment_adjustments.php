<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academy_payments', function (Blueprint $table): void {
            $table->unsignedBigInteger('reversed_minor')->default(0)->after('amount_minor');
        });

        Schema::create('academy_payment_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academy_id');
            $table->foreignId('academy_payment_id');
            $table->string('type', 24)->default('reversal');
            $table->unsignedBigInteger('amount_minor');
            $table->text('reason');
            $table->date('effective_on');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['academy_id', 'id']);
            $table->index(['academy_id', 'effective_on']);
            $table->foreign('academy_id')->references('id')->on('academies')->cascadeOnDelete();
            $table->foreign(['academy_id', 'academy_payment_id'], 'payment_adjustment_payment_fk')
                ->references(['academy_id', 'id'])->on('academy_payments')->restrictOnDelete();
        });

        Schema::table('academy_payment_allocations', function (Blueprint $table): void {
            $table->unique(['academy_id', 'id']);
        });

        Schema::create('academy_payment_adjustment_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academy_id');
            $table->foreignId('academy_payment_adjustment_id');
            $table->foreignId('academy_payment_allocation_id');
            $table->unsignedBigInteger('amount_minor');
            $table->timestamps();

            $table->foreign('academy_id')->references('id')->on('academies')->cascadeOnDelete();
            $table->foreign(['academy_id', 'academy_payment_adjustment_id'], 'adjustment_allocation_adjustment_fk')
                ->references(['academy_id', 'id'])->on('academy_payment_adjustments')->restrictOnDelete();
            $table->foreign(['academy_id', 'academy_payment_allocation_id'], 'adjustment_allocation_payment_fk')
                ->references(['academy_id', 'id'])->on('academy_payment_allocations')->restrictOnDelete();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('academy_payment_adjustment_allocations');
        Schema::dropIfExists('academy_payment_adjustments');
        Schema::table('academy_payment_allocations', fn (Blueprint $table) => $table->dropUnique(['academy_id', 'id']));
        Schema::table('academy_payments', fn (Blueprint $table) => $table->dropColumn('reversed_minor'));
    }
};
