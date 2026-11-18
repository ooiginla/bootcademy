<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academy_id');
            $table->string('code', 64);
            $table->string('name', 160);
            $table->string('discount_type', 20);
            $table->unsignedInteger('percentage_basis_points')->nullable();
            $table->unsignedBigInteger('fixed_amount_minor')->nullable();
            $table->char('currency', 3);
            $table->json('applies_to');
            $table->unsignedBigInteger('minimum_amount_minor')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->unsignedInteger('max_redemptions')->nullable();
            $table->unsignedInteger('per_customer_limit')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['academy_id', 'code']);
            $table->unique(['academy_id', 'id']);
            $table->index(['academy_id', 'is_active', 'starts_on', 'ends_on']);
            $table->foreign('academy_id')->references('id')->on('academies')->cascadeOnDelete();
        });

        Schema::create('coupon_redemptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academy_id');
            $table->foreignId('coupon_id');
            $table->foreignId('application_id')->nullable();
            $table->foreignId('store_order_id')->nullable();
            $table->foreignId('online_payment_intent_id')->nullable();
            $table->char('customer_key', 64);
            $table->char('currency', 3);
            $table->unsignedBigInteger('eligible_amount_minor');
            $table->unsignedBigInteger('discount_minor');
            $table->json('allocations')->nullable();
            $table->string('status', 20)->default('applied');
            $table->timestamp('applied_at');
            $table->timestamp('redeemed_at')->nullable();
            $table->timestamps();

            $table->unique(['coupon_id', 'application_id']);
            $table->unique(['coupon_id', 'store_order_id']);
            $table->unique('application_id');
            $table->unique('store_order_id');
            $table->index(['academy_id', 'coupon_id', 'status']);
            $table->index(['coupon_id', 'customer_key', 'status']);
            $table->foreign('academy_id')->references('id')->on('academies')->cascadeOnDelete();
            $table->foreign(['academy_id', 'coupon_id'], 'coupon_redemption_coupon_fk')
                ->references(['academy_id', 'id'])->on('coupons')->restrictOnDelete();
            $table->foreign(['academy_id', 'application_id'], 'coupon_redemption_application_fk')
                ->references(['academy_id', 'id'])->on('applications')->restrictOnDelete();
            $table->foreign(['academy_id', 'store_order_id'], 'coupon_redemption_store_order_fk')
                ->references(['academy_id', 'id'])->on('store_orders')->restrictOnDelete();
            $table->foreign('online_payment_intent_id')->references('id')->on('online_payment_intents')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_redemptions');
        Schema::dropIfExists('coupons');
    }
};
