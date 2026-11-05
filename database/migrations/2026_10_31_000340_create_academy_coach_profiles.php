<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academy_memberships', function (Blueprint $table): void {
            $table->unique(['academy_id', 'id'], 'memberships_academy_id_unique');
        });

        Schema::create('academy_coach_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academy_id');
            $table->foreignId('membership_id');
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100);
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 32)->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('whatsapp_number', 32)->nullable();
            $table->string('home_address', 500)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state_region', 100)->nullable();
            $table->string('country', 100)->nullable();
            $table->unsignedSmallInteger('years_of_experience')->nullable();
            $table->text('biography')->nullable();
            $table->timestamps();

            $table->unique(['academy_id', 'membership_id']);
            $table->unique(['academy_id', 'id']);
            $table->foreign('academy_id')->references('id')->on('academies')->cascadeOnDelete();
            $table->foreign(['academy_id', 'membership_id'], 'coach_profile_membership_fk')
                ->references(['academy_id', 'id'])->on('academy_memberships')->cascadeOnDelete();
        });

        Schema::create('coach_custom_field_values', function (Blueprint $table): void {
            $table->foreignId('academy_id');
            $table->foreignId('coach_profile_id');
            $table->foreignId('custom_field_id');
            $table->json('value')->nullable();
            $table->timestamps();

            $table->primary(['academy_id', 'coach_profile_id', 'custom_field_id']);
            $table->foreign('academy_id')->references('id')->on('academies')->cascadeOnDelete();
            $table->foreign(['academy_id', 'coach_profile_id'], 'ccfv_profile_fk')
                ->references(['academy_id', 'id'])->on('academy_coach_profiles')->cascadeOnDelete();
            $table->foreign(['academy_id', 'custom_field_id'], 'ccfv_field_fk')
                ->references(['academy_id', 'id'])->on('academy_custom_fields')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coach_custom_field_values');
        Schema::dropIfExists('academy_coach_profiles');
        Schema::table('academy_memberships', function (Blueprint $table): void {
            $table->dropUnique('memberships_academy_id_unique');
        });
    }
};
