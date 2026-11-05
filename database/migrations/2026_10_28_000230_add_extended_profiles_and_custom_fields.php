<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table): void {
            $table->string('middle_name', 100)->nullable()->after('first_name');
            $table->string('gender', 32)->nullable()->after('date_of_birth');
            $table->string('phone', 32)->nullable()->after('gender');
            $table->string('whatsapp_number', 32)->nullable()->after('phone');
            $table->string('home_address', 500)->nullable()->after('whatsapp_number');
            $table->string('city', 100)->nullable()->after('home_address');
            $table->string('state_region', 100)->nullable()->after('city');
            $table->string('country', 100)->nullable()->after('state_region');
        });

        Schema::table('guardians', function (Blueprint $table): void {
            $table->string('whatsapp_number', 32)->nullable()->after('phone');
            $table->string('signature_path', 500)->nullable()->after('whatsapp_number');
        });

        Schema::create('academy_custom_fields', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academy_id')->constrained()->cascadeOnDelete();
            $table->string('entity_type', 32)->default('player');
            $table->string('key', 64);
            $table->string('label', 160);
            $table->string('type', 24);
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_sensitive')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['academy_id', 'entity_type', 'key']);
            $table->unique(['academy_id', 'id']);
            $table->index(['academy_id', 'entity_type', 'is_active']);
        });

        Schema::create('player_custom_field_values', function (Blueprint $table): void {
            $table->foreignId('academy_id');
            $table->foreignId('player_id');
            $table->foreignId('custom_field_id');
            $table->json('value')->nullable();
            $table->timestamps();

            $table->primary(['academy_id', 'player_id', 'custom_field_id']);
            $table->foreign('academy_id')->references('id')->on('academies')->cascadeOnDelete();
            $table->foreign(['academy_id', 'player_id'], 'pcfv_player_fk')
                ->references(['academy_id', 'id'])->on('players')->cascadeOnDelete();
            $table->foreign(['academy_id', 'custom_field_id'], 'pcfv_field_fk')
                ->references(['academy_id', 'id'])->on('academy_custom_fields')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_custom_field_values');
        Schema::dropIfExists('academy_custom_fields');
        Schema::table('guardians', fn (Blueprint $table) => $table->dropColumn(['whatsapp_number', 'signature_path']));
        Schema::table('players', fn (Blueprint $table) => $table->dropColumn([
            'middle_name', 'gender', 'phone', 'whatsapp_number', 'home_address',
            'city', 'state_region', 'country',
        ]));
    }
};
