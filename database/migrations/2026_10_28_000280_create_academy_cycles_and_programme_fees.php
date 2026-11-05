<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academy_cycles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academy_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('type', 24);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->unsignedSmallInteger('duration_months')->nullable();
            $table->string('status', 24)->default('draft');
            $table->timestamps();

            $table->unique(['academy_id', 'name']);
            $table->unique(['academy_id', 'id']);
            $table->index(['academy_id', 'status']);
        });

        Schema::create('programme_cycles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academy_id');
            $table->foreignId('academy_cycle_id');
            $table->foreignId('programme_id');
            $table->char('currency', 3);
            $table->unsignedBigInteger('application_fee_minor')->default(0);
            $table->string('payment_mode', 24)->default('one_off');
            $table->string('enrollment_payment_rule', 32)->default('full_payment');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['academy_id', 'academy_cycle_id', 'programme_id'], 'programme_cycle_unique');
            $table->unique(['academy_id', 'id']);
            $table->foreign('academy_id')->references('id')->on('academies')->cascadeOnDelete();
            $table->foreign(['academy_id', 'academy_cycle_id'], 'programme_cycle_cycle_fk')
                ->references(['academy_id', 'id'])->on('academy_cycles')->cascadeOnDelete();
            $table->foreign(['academy_id', 'programme_id'], 'programme_cycle_programme_fk')
                ->references(['academy_id', 'id'])->on('programmes')->restrictOnDelete();
        });

        Schema::create('programme_cycle_fee_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academy_id');
            $table->foreignId('programme_cycle_id');
            $table->string('name', 120);
            $table->unsignedBigInteger('amount_minor');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('academy_id')->references('id')->on('academies')->cascadeOnDelete();
            $table->foreign(['academy_id', 'programme_cycle_id'], 'programme_cycle_fee_parent_fk')
                ->references(['academy_id', 'id'])->on('programme_cycles')->cascadeOnDelete();
            $table->index(['programme_cycle_id', 'sort_order']);
        });

        Schema::create('programme_cycle_installments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academy_id');
            $table->foreignId('programme_cycle_id');
            $table->string('name', 120);
            $table->unsignedBigInteger('amount_minor');
            $table->date('due_on')->nullable();
            $table->unsignedSmallInteger('due_offset_days')->nullable();
            $table->unsignedSmallInteger('sequence');
            $table->timestamps();

            $table->unique(['programme_cycle_id', 'sequence']);
            $table->foreign('academy_id')->references('id')->on('academies')->cascadeOnDelete();
            $table->foreign(['academy_id', 'programme_cycle_id'], 'programme_cycle_installment_parent_fk')
                ->references(['academy_id', 'id'])->on('programme_cycles')->cascadeOnDelete();
        });

        Schema::table('applications', function (Blueprint $table): void {
            $table->foreignId('programme_cycle_id')->nullable()->after('programme_id');
            $table->foreign(['academy_id', 'programme_cycle_id'], 'application_programme_cycle_fk')
                ->references(['academy_id', 'id'])->on('programme_cycles')->restrictOnDelete();
        });

        Schema::table('enrollments', function (Blueprint $table): void {
            $table->foreignId('academy_cycle_id')->nullable()->after('programme_id');
            $table->foreignId('programme_cycle_id')->nullable()->after('academy_cycle_id');
            $table->foreign(['academy_id', 'academy_cycle_id'], 'enrollment_cycle_fk')
                ->references(['academy_id', 'id'])->on('academy_cycles')->restrictOnDelete();
            $table->foreign(['academy_id', 'programme_cycle_id'], 'enrollment_programme_cycle_fk')
                ->references(['academy_id', 'id'])->on('programme_cycles')->restrictOnDelete();
            $table->index(['academy_id', 'academy_cycle_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table): void {
            $table->dropForeign('enrollment_programme_cycle_fk');
            $table->dropForeign('enrollment_cycle_fk');
            $table->dropIndex(['academy_id', 'academy_cycle_id', 'status']);
            $table->dropColumn(['academy_cycle_id', 'programme_cycle_id']);
        });
        Schema::table('applications', function (Blueprint $table): void {
            $table->dropForeign('application_programme_cycle_fk');
            $table->dropColumn('programme_cycle_id');
        });
        Schema::dropIfExists('programme_cycle_installments');
        Schema::dropIfExists('programme_cycle_fee_items');
        Schema::dropIfExists('programme_cycles');
        Schema::dropIfExists('academy_cycles');
    }
};
