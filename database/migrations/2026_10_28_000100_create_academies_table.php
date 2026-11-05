<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academies', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 160);
            $table->string('slug', 180)->unique();
            $table->string('academy_type', 80);
            $table->string('timezone', 64);
            $table->char('currency', 3);
            $table->string('logo_path', 500)->nullable();
            $table->char('primary_color', 7)->nullable();
            $table->char('secondary_color', 7)->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['academy_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academies');
    }
};
