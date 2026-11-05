<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academies', function (Blueprint $table): void {
            $table->string('bank_name', 120)->nullable()->after('currency');
            $table->string('bank_account_name', 160)->nullable()->after('bank_name');
            $table->string('bank_account_number', 64)->nullable()->after('bank_account_name');
        });
    }

    public function down(): void
    {
        Schema::table('academies', function (Blueprint $table): void {
            $table->dropColumn(['bank_name', 'bank_account_name', 'bank_account_number']);
        });
    }
};
