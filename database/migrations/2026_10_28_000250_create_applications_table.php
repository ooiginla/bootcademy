<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academy_id');
            $table->string('reference', 24);
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100);
            $table->date('date_of_birth')->nullable();
            $table->string('guardian_first_name', 100)->nullable();
            $table->string('guardian_last_name', 100)->nullable();
            $table->string('guardian_email', 254)->nullable();
            $table->string('guardian_phone', 32)->nullable();
            $table->string('guardian_relationship', 80)->nullable();
            $table->foreignId('programme_id')->nullable();
            $table->foreignId('branch_id')->nullable();
            $table->json('custom_fields')->nullable();
            $table->string('status', 24)->default('submitted');
            $table->text('review_notes')->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('converted_player_id')->nullable();
            $table->timestamp('enrolled_at')->nullable();
            $table->timestamps();

            $table->unique(['academy_id', 'reference']);
            $table->unique(['academy_id', 'id']);
            $table->index(['academy_id', 'status', 'created_at']);
            $table->index(['academy_id', 'last_name', 'first_name']);
            $table->foreign('academy_id')->references('id')->on('academies')->cascadeOnDelete();
            $table->foreign(['academy_id', 'programme_id'], 'application_programme_fk')->references(['academy_id', 'id'])->on('programmes')->restrictOnDelete();
            $table->foreign(['academy_id', 'branch_id'], 'application_branch_fk')->references(['academy_id', 'id'])->on('branches')->restrictOnDelete();
            $table->foreign(['academy_id', 'converted_player_id'], 'application_player_fk')->references(['academy_id', 'id'])->on('players')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
