<?php

use App\Platform\Subscriptions\DefaultPlanCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->string('description', 255)->nullable()->after('name');
            // Null means no limit.
            $table->unsignedInteger('branch_limit')->nullable()->after('active_player_limit');
            $table->unsignedInteger('staff_account_limit')->nullable()->after('branch_limit');
            $table->boolean('is_recommended')->default(false)->after('staff_account_limit');
            $table->string('migration_support', 80)->nullable()->after('is_recommended');
            $table->string('support_level', 120)->nullable()->after('migration_support');
        });

        // A platform-set price for one academy. It replaces the plan's list price for that academy.
        Schema::create('academy_plan_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('monthly_price_minor');
            $table->unsignedBigInteger('quarterly_price_minor');
            $table->unsignedBigInteger('annual_price_minor');
            $table->foreignId('set_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['academy_id', 'plan_id']);
        });

        // An academy paying the platform for a subscription period. Never deleted.
        Schema::create('subscription_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->string('billing_interval', 16);
            $table->char('currency', 3);
            $table->unsignedBigInteger('amount_minor');
            $table->unsignedBigInteger('surcharge_minor')->default(0);
            $table->unsignedBigInteger('platform_fee_minor')->default(0);
            $table->unsignedBigInteger('paystack_fee_estimate_minor')->default(0);
            $table->unsignedBigInteger('charged_amount_minor');
            $table->unsignedBigInteger('provider_fee_minor')->nullable();
            $table->string('provider', 30)->default('paystack');
            $table->string('reference', 80)->unique();
            $table->string('access_code', 120)->nullable();
            $table->string('subaccount_code', 40)->nullable();
            $table->string('status', 24)->default('pending');
            $table->string('failure_reason', 500)->nullable();
            $table->foreignId('initiated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at'], 'subscription_payments_requery_index');
            $table->index(['academy_id', 'created_at']);
        });

        // A span of time an academy has access, from a trial, a payment or a platform grant.
        Schema::create('academy_subscription_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academy_subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->string('kind', 16);
            $table->string('billing_interval', 16)->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->foreignId('subscription_payment_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('granted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->index(['academy_id', 'starts_at', 'ends_at'], 'academy_subscription_periods_window_index');
        });

        Schema::table('platform_settings', function (Blueprint $table): void {
            $table->foreignId('trial_plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->foreignId('post_trial_plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->unsignedSmallInteger('trial_days')->default(30);
            // The platform's own settlement account for subscription payments.
            $table->string('bank_code', 20)->nullable();
            $table->string('bank_name', 120)->nullable();
            $table->string('bank_account_name', 160)->nullable();
            $table->string('bank_account_number', 20)->nullable();
            $table->string('paystack_subaccount_code', 40)->nullable();
            $table->string('paystack_subaccount_status', 24)->default('none');
            $table->string('paystack_subaccount_error', 500)->nullable();
            $table->timestamp('paystack_subaccount_synced_at')->nullable();
        });

        $this->seedPlans();
    }

    public function down(): void
    {
        Schema::table('platform_settings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('trial_plan_id');
            $table->dropConstrainedForeignId('post_trial_plan_id');
            $table->dropColumn([
                'trial_days', 'bank_code', 'bank_name', 'bank_account_name', 'bank_account_number',
                'paystack_subaccount_code', 'paystack_subaccount_status', 'paystack_subaccount_error',
                'paystack_subaccount_synced_at',
            ]);
        });
        Schema::dropIfExists('academy_subscription_periods');
        Schema::dropIfExists('subscription_payments');
        Schema::dropIfExists('academy_plan_prices');
        Schema::table('plans', function (Blueprint $table): void {
            $table->dropColumn([
                'description', 'branch_limit', 'staff_account_limit', 'is_recommended', 'migration_support', 'support_level',
            ]);
        });
    }

    /**
     * Sets the launch prices and limits, then starts a free trial for every existing academy
     * from today, as agreed for academies that were live before subscriptions were charged.
     */
    private function seedPlans(): void
    {
        $now = now();
        $ids = [];
        foreach (DefaultPlanCatalog::plans() as $plan) {
            $features = $plan['features'];
            unset($plan['features']);
            DB::table('plans')->updateOrInsert(
                ['code' => $plan['code']],
                [...$plan, 'currency' => 'NGN', 'is_active' => true, 'updated_at' => $now],
            );
            $id = (int) DB::table('plans')->where('code', $plan['code'])->value('id');
            DB::table('plans')->where('id', $id)->whereNull('created_at')->update(['created_at' => $now]);
            foreach ($features as $key => $enabled) {
                DB::table('plan_features')->updateOrInsert(
                    ['plan_id' => $id, 'feature_key' => $key],
                    ['enabled' => $enabled, 'limit' => null, 'created_at' => $now, 'updated_at' => $now],
                );
            }
            $ids[$plan['code']] = $id;
        }

        $settingsId = DB::table('platform_settings')->orderBy('id')->value('id')
            ?? DB::table('platform_settings')->insertGetId(['created_at' => $now, 'updated_at' => $now]);
        DB::table('platform_settings')->where('id', $settingsId)->update([
            'trial_plan_id' => $ids['enterprise'],
            'post_trial_plan_id' => $ids['essential'],
            'trial_days' => 30,
        ]);

        $trialEnds = $now->copy()->addDays(30);
        DB::table('academies')->orderBy('id')->select('id')->each(function (object $academy) use ($ids, $now, $trialEnds): void {
            $subscriptionId = DB::table('academy_subscriptions')->where('academy_id', $academy->id)->value('id');
            if ($subscriptionId === null) {
                $subscriptionId = DB::table('academy_subscriptions')->insertGetId([
                    'academy_id' => $academy->id,
                    'plan_id' => $ids['enterprise'],
                    'status' => 'active',
                    'starts_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                DB::table('academy_subscriptions')->where('id', $subscriptionId)->update(['plan_id' => $ids['enterprise'], 'updated_at' => $now]);
            }
            DB::table('academy_subscription_periods')->insert([
                'academy_id' => $academy->id,
                'academy_subscription_id' => $subscriptionId,
                'plan_id' => $ids['enterprise'],
                'kind' => 'trial',
                'starts_at' => $now,
                'ends_at' => $trialEnds,
                'note' => 'Free trial for academies live before subscriptions were charged.',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });
    }
};
