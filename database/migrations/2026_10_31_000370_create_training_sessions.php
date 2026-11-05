<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academy_id');
            $table->foreignId('branch_id');
            $table->foreignId('programme_id');
            $table->foreignId('coach_membership_id')->nullable();
            $table->string('title', 160);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->string('location', 255)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 24)->default('scheduled');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['academy_id', 'id']);
            $table->index(['academy_id', 'starts_at']);
            $table->index(['academy_id', 'branch_id', 'starts_at']);
            $table->index(['academy_id', 'coach_membership_id', 'starts_at']);
            $table->foreign('academy_id')->references('id')->on('academies')->cascadeOnDelete();
            $table->foreign(['academy_id', 'branch_id'], 'session_branch_fk')->references(['academy_id', 'id'])->on('branches')->restrictOnDelete();
            $table->foreign(['academy_id', 'programme_id'], 'session_programme_fk')->references(['academy_id', 'id'])->on('programmes')->restrictOnDelete();
            $table->foreign(['academy_id', 'coach_membership_id'], 'session_coach_fk')->references(['academy_id', 'id'])->on('academy_memberships')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_sessions');
    }
};
