<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academy_id');
            $table->foreignId('training_session_id');
            $table->foreignId('player_id');
            $table->string('status', 16);
            $table->string('notes', 1000)->nullable();
            $table->foreignId('marked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['academy_id', 'id']);
            $table->unique(['academy_id', 'training_session_id', 'player_id'], 'attendance_session_player_unique');
            $table->index(['academy_id', 'player_id', 'status']);
            $table->foreign('academy_id')->references('id')->on('academies')->cascadeOnDelete();
            $table->foreign(['academy_id', 'training_session_id'], 'attendance_session_fk')->references(['academy_id', 'id'])->on('training_sessions')->cascadeOnDelete();
            $table->foreign(['academy_id', 'player_id'], 'attendance_player_fk')->references(['academy_id', 'id'])->on('players')->restrictOnDelete();
        });

        Schema::create('attendance_change_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academy_id');
            $table->foreignId('attendance_record_id');
            $table->string('old_status', 16);
            $table->string('new_status', 16);
            $table->string('old_notes', 1000)->nullable();
            $table->string('new_notes', 1000)->nullable();
            $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');

            $table->index(['academy_id', 'attendance_record_id', 'created_at'], 'attendance_changes_record_index');
            $table->foreign('academy_id')->references('id')->on('academies')->cascadeOnDelete();
            $table->foreign(['academy_id', 'attendance_record_id'], 'attendance_change_record_fk')->references(['academy_id', 'id'])->on('attendance_records')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_change_logs');
        Schema::dropIfExists('attendance_records');
    }
};
