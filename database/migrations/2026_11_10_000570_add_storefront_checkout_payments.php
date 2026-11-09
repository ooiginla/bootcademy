<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_orders', function (Blueprint $table): void {
            $table->string('member_name', 160)->nullable()->after('customer_phone');
            $table->string('payment_preference', 24)->default('pay_later')->after('payment_status');
        });

        Schema::table('store_order_payments', function (Blueprint $table): void {
            $table->unsignedBigInteger('surcharge_minor')->default(0)->after('amount_minor');
            $table->unsignedBigInteger('charged_amount_minor')->default(0)->after('surcharge_minor');
            $table->unsignedBigInteger('provider_fee_minor')->nullable()->after('charged_amount_minor');
            $table->string('access_code', 160)->nullable()->after('external_reference');
            $table->string('failure_reason', 500)->nullable()->after('access_code');
            $table->timestamp('initialized_at')->nullable()->after('failure_reason');
            $table->timestamp('verified_at')->nullable()->after('initialized_at');
            $table->timestamp('receipt_queued_at')->nullable()->after('verified_at');
        });

        DB::table('store_order_payments')->update([
            'charged_amount_minor' => DB::raw('amount_minor'),
        ]);
    }

    public function down(): void
    {
        Schema::table('store_order_payments', function (Blueprint $table): void {
            $table->dropColumn([
                'surcharge_minor', 'charged_amount_minor', 'provider_fee_minor', 'access_code',
                'failure_reason', 'initialized_at', 'verified_at', 'receipt_queued_at',
            ]);
        });

        Schema::table('store_orders', function (Blueprint $table): void {
            $table->dropColumn(['member_name', 'payment_preference']);
        });
    }
};
