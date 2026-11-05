<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academy_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('code', 32)->nullable();
            $table->string('address_line', 255)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('state_region', 120)->nullable();
            $table->char('country_code', 2)->nullable();
            $table->string('timezone', 64);
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 40)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['academy_id', 'name']);
            $table->unique(['academy_id', 'code']);
            $table->index(['academy_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
