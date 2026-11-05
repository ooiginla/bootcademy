<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table): void {
            $table->string('internal_identifier', 64)->nullable()->after('reference');
            $table->string('gender', 32)->nullable()->after('date_of_birth');
            $table->string('phone', 32)->nullable()->after('gender');
            $table->string('whatsapp_number', 32)->nullable()->after('phone');
            $table->string('home_address', 500)->nullable()->after('whatsapp_number');
            $table->string('city', 100)->nullable()->after('home_address');
            $table->string('state_region', 100)->nullable()->after('city');
            $table->string('country', 100)->nullable()->after('state_region');
            $table->string('guardian_whatsapp_number', 32)->nullable()->after('guardian_phone');
            $table->string('guardian_signature_path', 500)->nullable()->after('guardian_relationship');
            $table->unique(['academy_id', 'internal_identifier']);
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table): void {
            $table->dropColumn([
                'internal_identifier', 'gender', 'phone', 'whatsapp_number', 'home_address', 'city', 'state_region', 'country',
                'guardian_whatsapp_number', 'guardian_signature_path',
            ]);
        });
    }
};
