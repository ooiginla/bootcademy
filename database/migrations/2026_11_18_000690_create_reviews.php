<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academy_id')->constrained()->restrictOnDelete();
            $table->string('title', 160);
            $table->text('description')->nullable();
            $table->string('audience', 20);
            $table->string('status', 20)->default('draft');
            $table->boolean('collect_respondent_name')->default(false);
            $table->json('fields');
            $table->string('public_token', 64)->unique();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['academy_id', 'status', 'created_at']);
            $table->index(['academy_id', 'audience']);
        });

        Schema::create('review_responses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academy_id')->constrained()->restrictOnDelete();
            $table->foreignId('review_id')->constrained()->cascadeOnDelete();
            $table->string('respondent_name', 160)->nullable();
            $table->json('answers');
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->index(['academy_id', 'review_id', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_responses');
        Schema::dropIfExists('reviews');
    }
};
