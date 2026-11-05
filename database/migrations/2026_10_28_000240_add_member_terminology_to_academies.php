<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academies', function (Blueprint $table): void {
            $table->string('member_label_singular', 50)->default('Student')->after('academy_type');
            $table->string('member_label_plural', 50)->default('Students')->after('member_label_singular');
        });
    }

    public function down(): void
    {
        Schema::table('academies', function (Blueprint $table): void {
            $table->dropColumn(['member_label_singular', 'member_label_plural']);
        });
    }
};
