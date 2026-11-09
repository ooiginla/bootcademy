<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academy_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('is_enabled')->default(false);
            $table->string('qualifying_event', 40)->default('first_verified_payment');
            $table->string('reward_type', 20)->default('percentage');
            $table->unsignedSmallInteger('reward_basis_points')->nullable();
            $table->unsignedBigInteger('fixed_reward_minor')->nullable();
            $table->string('eligible_payment_scope', 24)->default('programme');
            $table->timestamps();
        });

        Schema::create('referrals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academy_id');
            $table->foreignId('referrer_player_id');
            $table->foreignId('referred_application_id')->nullable();
            $table->foreignId('referred_player_id')->nullable();
            $table->string('status', 24)->default('attributed');
            $table->string('source', 24)->default('admin');
            $table->text('notes')->nullable();
            $table->timestamp('attributed_at');
            $table->timestamp('qualified_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['academy_id', 'id']);
            $table->unique(['academy_id', 'referred_application_id']);
            $table->unique(['academy_id', 'referred_player_id']);
            $table->index(['academy_id', 'status', 'attributed_at']);
            $table->foreign('academy_id')->references('id')->on('academies')->cascadeOnDelete();
            $table->foreign(['academy_id', 'referrer_player_id'], 'referral_referrer_fk')->references(['academy_id', 'id'])->on('players')->restrictOnDelete();
            $table->foreign(['academy_id', 'referred_application_id'], 'referral_application_fk')->references(['academy_id', 'id'])->on('applications')->restrictOnDelete();
            $table->foreign(['academy_id', 'referred_player_id'], 'referral_player_fk')->references(['academy_id', 'id'])->on('players')->restrictOnDelete();
        });

        Schema::create('referral_rewards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academy_id');
            $table->foreignId('referral_id');
            $table->foreignId('academy_payment_id')->nullable();
            $table->foreignId('academy_invoice_id')->nullable();
            $table->string('event_key', 100);
            $table->char('currency', 3);
            $table->unsignedBigInteger('qualifying_amount_minor')->default(0);
            $table->unsignedBigInteger('reward_amount_minor');
            $table->string('status', 24)->default('pending');
            $table->timestamp('earned_at');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('paid_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('payout_reference', 120)->nullable();
            $table->text('payout_notes')->nullable();
            $table->timestamps();

            $table->unique(['academy_id', 'id']);
            $table->unique(['academy_id', 'event_key']);
            $table->index(['academy_id', 'status', 'earned_at']);
            $table->foreign('academy_id')->references('id')->on('academies')->cascadeOnDelete();
            $table->foreign(['academy_id', 'referral_id'], 'reward_referral_fk')->references(['academy_id', 'id'])->on('referrals')->restrictOnDelete();
            $table->foreign(['academy_id', 'academy_payment_id'], 'reward_payment_fk')->references(['academy_id', 'id'])->on('academy_payments')->restrictOnDelete();
            $table->foreign(['academy_id', 'academy_invoice_id'], 'reward_invoice_fk')->references(['academy_id', 'id'])->on('academy_invoices')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_rewards');
        Schema::dropIfExists('referrals');
        Schema::dropIfExists('referral_settings');
    }
};
