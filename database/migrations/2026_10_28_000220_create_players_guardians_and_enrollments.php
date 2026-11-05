<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guardians', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academy_id')->constrained()->cascadeOnDelete();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email', 254)->nullable();
            $table->string('phone', 32);
            $table->timestamps();

            $table->unique(['academy_id', 'id']);
            $table->index(['academy_id', 'last_name', 'first_name']);
            $table->index(['academy_id', 'email']);
        });

        Schema::create('players', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academy_id')->constrained()->cascadeOnDelete();
            $table->string('internal_identifier', 64)->nullable();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->date('date_of_birth')->nullable();
            $table->string('status', 24)->default('active');
            $table->timestamps();

            $table->unique(['academy_id', 'internal_identifier']);
            $table->unique(['academy_id', 'id']);
            $table->index(['academy_id', 'status']);
            $table->index(['academy_id', 'last_name', 'first_name']);
        });

        Schema::create('player_guardian', function (Blueprint $table): void {
            $table->foreignId('academy_id');
            $table->foreignId('player_id');
            $table->foreignId('guardian_id');
            $table->string('relationship', 80)->default('Guardian');
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_emergency_contact')->default(false);
            $table->boolean('is_authorized_pickup')->default(false);
            $table->timestamps();

            $table->primary(['academy_id', 'player_id', 'guardian_id']);
            $table->foreign('academy_id')->references('id')->on('academies')->cascadeOnDelete();
            $table->foreign(['academy_id', 'player_id'], 'pg_player_fk')
                ->references(['academy_id', 'id'])->on('players')->cascadeOnDelete();
            $table->foreign(['academy_id', 'guardian_id'], 'pg_guardian_fk')
                ->references(['academy_id', 'id'])->on('guardians')->cascadeOnDelete();
        });

        Schema::create('enrollments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academy_id');
            $table->foreignId('player_id');
            $table->foreignId('programme_id');
            $table->foreignId('branch_id')->nullable();
            $table->string('status', 24)->default('active');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->timestamps();

            $table->unique(['academy_id', 'id']);
            $table->index(['academy_id', 'player_id', 'status']);
            $table->foreign('academy_id')->references('id')->on('academies')->cascadeOnDelete();
            $table->foreign(['academy_id', 'player_id'], 'enrollment_player_fk')
                ->references(['academy_id', 'id'])->on('players')->cascadeOnDelete();
            $table->foreign(['academy_id', 'programme_id'], 'enrollment_programme_fk')
                ->references(['academy_id', 'id'])->on('programmes')->restrictOnDelete();
            $table->foreign(['academy_id', 'branch_id'], 'enrollment_branch_fk')
                ->references(['academy_id', 'id'])->on('branches')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
        Schema::dropIfExists('player_guardian');
        Schema::dropIfExists('players');
        Schema::dropIfExists('guardians');
    }
};
