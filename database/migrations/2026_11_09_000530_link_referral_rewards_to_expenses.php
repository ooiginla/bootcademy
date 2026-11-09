<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academy_expenses', function (Blueprint $table): void {
            $table->foreignId('referral_reward_id')->nullable()->after('academy_invoice_write_off_id');
            $table->unique('referral_reward_id');
            $table->foreign(['academy_id', 'referral_reward_id'], 'expense_referral_reward_fk')
                ->references(['academy_id', 'id'])->on('referral_rewards')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('academy_expenses', function (Blueprint $table): void {
            $table->dropForeign('expense_referral_reward_fk');
            $table->dropUnique(['referral_reward_id']);
            $table->dropColumn('referral_reward_id');
        });
    }
};
