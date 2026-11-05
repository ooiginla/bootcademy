<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academies', function (Blueprint $table): void {
            $table->timestamp('support_access_approved_until')->nullable()->after('secondary_color');
            $table->foreignId('support_access_approved_by_user_id')
                ->nullable()
                ->after('support_access_approved_until')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('academies', function (Blueprint $table): void {
            $table->dropForeign(['support_access_approved_by_user_id']);
            $table->dropColumn([
                'support_access_approved_until',
                'support_access_approved_by_user_id',
            ]);
        });
    }
};
