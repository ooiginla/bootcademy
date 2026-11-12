<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row of platform-wide online payment settings. Amounts are minor units (kobo).
        Schema::create('platform_settings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('paystack_fee_basis_points')->default(150);
            $table->unsignedBigInteger('paystack_fee_fixed_minor')->default(10000);
            $table->unsignedBigInteger('paystack_fee_cap_minor')->nullable()->default(200000);
            $table->unsignedBigInteger('paystack_fee_waiver_threshold_minor')->nullable()->default(250000);
            // The platform's own fee starts at zero until the platform admin sets it.
            $table->unsignedSmallInteger('platform_fee_basis_points')->default(0);
            $table->unsignedBigInteger('platform_fee_fixed_minor')->default(0);
            $table->timestamps();
        });
        DB::table('platform_settings')->insert(['created_at' => now(), 'updated_at' => now()]);

        Schema::table('academies', function (Blueprint $table): void {
            // Null means "use the platform default".
            $table->unsignedSmallInteger('platform_fee_basis_points')->nullable()->after('paystack_fee_waiver_threshold_minor');
            $table->unsignedBigInteger('platform_fee_fixed_minor')->nullable()->after('platform_fee_basis_points');
            $table->string('bank_code', 20)->nullable()->after('bank_account_number');
            $table->string('paystack_subaccount_code', 40)->nullable()->unique()->after('bank_code');
            $table->string('paystack_subaccount_status', 24)->default('none')->after('paystack_subaccount_code');
            $table->string('paystack_subaccount_error', 500)->nullable()->after('paystack_subaccount_status');
            $table->timestamp('paystack_subaccount_synced_at')->nullable()->after('paystack_subaccount_error');
        });

        foreach (['online_payment_intents', 'store_order_payments'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->unsignedBigInteger('platform_fee_minor')->default(0)->after('surcharge_minor');
                $table->unsignedBigInteger('paystack_fee_estimate_minor')->default(0)->after('platform_fee_minor');
                $table->string('subaccount_code', 40)->nullable()->after('paystack_fee_estimate_minor');
                $table->timestamp('last_checked_at')->nullable();
                $table->index(['status', 'created_at'], $table->getTable().'_requery_index');
            });
        }

        Schema::table('academy_payments', function (Blueprint $table): void {
            $table->unsignedBigInteger('platform_fee_minor')->default(0)->after('customer_fee_minor');
        });
    }

    public function down(): void
    {
        Schema::table('academy_payments', fn (Blueprint $table) => $table->dropColumn('platform_fee_minor'));
        foreach (['online_payment_intents', 'store_order_payments'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->dropIndex($tableName.'_requery_index');
                $table->dropColumn(['platform_fee_minor', 'paystack_fee_estimate_minor', 'subaccount_code', 'last_checked_at']);
            });
        }
        Schema::table('academies', function (Blueprint $table): void {
            $table->dropUnique(['paystack_subaccount_code']);
            $table->dropColumn([
                'platform_fee_basis_points', 'platform_fee_fixed_minor', 'bank_code', 'paystack_subaccount_code',
                'paystack_subaccount_status', 'paystack_subaccount_error', 'paystack_subaccount_synced_at',
            ]);
        });
        Schema::dropIfExists('platform_settings');
    }
};
