<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->string('transfer_reference', 40)->nullable()->after('reserved_delta');
            $table->index(['academy_id', 'transfer_reference']);
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropIndex(['academy_id', 'transfer_reference']);
            $table->dropColumn('transfer_reference');
        });
    }
};
