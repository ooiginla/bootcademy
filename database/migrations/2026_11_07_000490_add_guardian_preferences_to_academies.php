<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academies', function (Blueprint $table): void {
            $table->boolean('collect_guardian_information')->default(true)->after('staff_label_plural');
            $table->string('guardian_label_singular', 50)->default('Guardian')->after('collect_guardian_information');
            $table->string('guardian_label_plural', 50)->default('Guardians')->after('guardian_label_singular');
        });
    }

    public function down(): void
    {
        Schema::table('academies', fn (Blueprint $table) => $table->dropColumn([
            'collect_guardian_information', 'guardian_label_singular', 'guardian_label_plural',
        ]));
    }
};
