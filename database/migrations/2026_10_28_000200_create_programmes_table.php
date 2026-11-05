<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table): void {
            $table->unique(['academy_id', 'id']);
        });

        Schema::create('programmes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academy_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('code', 32)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['academy_id', 'name']);
            $table->unique(['academy_id', 'code']);
            $table->unique(['academy_id', 'id']);
            $table->index(['academy_id', 'is_active']);
        });

        Schema::create('programme_branch', function (Blueprint $table): void {
            $table->foreignId('academy_id');
            $table->foreignId('programme_id');
            $table->foreignId('branch_id');
            $table->timestamps();

            $table->primary(['academy_id', 'programme_id', 'branch_id']);
            $table->foreign('academy_id')->references('id')->on('academies')->cascadeOnDelete();
            $table->foreign(['academy_id', 'programme_id'])
                ->references(['academy_id', 'id'])
                ->on('programmes')
                ->cascadeOnDelete();
            $table->foreign(['academy_id', 'branch_id'])
                ->references(['academy_id', 'id'])
                ->on('branches')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programme_branch');
        Schema::dropIfExists('programmes');

        Schema::table('branches', function (Blueprint $table): void {
            $table->dropUnique(['academy_id', 'id']);
        });
    }
};
