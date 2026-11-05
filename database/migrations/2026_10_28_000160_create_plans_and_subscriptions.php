<?php

use App\Enums\SubscriptionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('name', 100);
            $table->char('currency', 3)->default('NGN');
            $table->unsignedBigInteger('monthly_price_minor');
            $table->unsignedBigInteger('quarterly_price_minor');
            $table->unsignedBigInteger('annual_price_minor');
            $table->unsignedInteger('active_player_limit');
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('plan_features', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->string('feature_key', 100);
            $table->boolean('enabled')->default(false);
            $table->unsignedBigInteger('limit')->nullable();
            $table->timestamps();

            $table->unique(['plan_id', 'feature_key']);
        });

        Schema::create('academy_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academy_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->enum('status', array_column(SubscriptionStatus::cases(), 'value'))
                ->default(SubscriptionStatus::Active->value);
            $table->timestamp('starts_at');
            $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'plan_id']);
        });

        Schema::create('subscription_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academy_subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 80);
            $table->json('before')->nullable();
            $table->json('after');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['academy_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_events');
        Schema::dropIfExists('academy_subscriptions');
        Schema::dropIfExists('plan_features');
        Schema::dropIfExists('plans');
    }
};
