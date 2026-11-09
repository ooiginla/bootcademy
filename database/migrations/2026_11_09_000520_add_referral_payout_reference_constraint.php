<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referral_rewards', fn (Blueprint $table) => $table->unique(
            ['academy_id', 'payout_reference'],
            'referral_reward_payout_reference_unique',
        ));
    }

    public function down(): void
    {
        Schema::table('referral_rewards', fn (Blueprint $table) => $table->dropUnique(
            'referral_reward_payout_reference_unique',
        ));
    }
};
