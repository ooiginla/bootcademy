<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academies', function (Blueprint $table): void {
            $table->string('staff_label_singular', 50)->default('Coach')->after('member_label_plural');
            $table->string('staff_label_plural', 50)->default('Coaches')->after('staff_label_singular');
        });
    }

    public function down(): void
    {
        Schema::table('academies', fn (Blueprint $table) => $table->dropColumn(['staff_label_singular', 'staff_label_plural']));
    }
};
