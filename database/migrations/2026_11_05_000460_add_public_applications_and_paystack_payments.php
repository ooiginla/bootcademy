<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academies', function (Blueprint $table): void {
            $table->unsignedSmallInteger('paystack_fee_basis_points')->default(150)->after('bank_account_number');
            $table->unsignedBigInteger('paystack_fee_fixed_minor')->default(10000)->after('paystack_fee_basis_points');
            $table->unsignedBigInteger('paystack_fee_cap_minor')->nullable()->default(200000)->after('paystack_fee_fixed_minor');
            $table->unsignedBigInteger('paystack_fee_waiver_threshold_minor')->nullable()->default(250000)->after('paystack_fee_cap_minor');
        });
        Schema::table('applications', function (Blueprint $table): void {
            $table->string('email', 254)->nullable()->after('last_name');
            $table->char('public_access_token_hash', 64)->nullable()->unique()->after('submission_hash');
        });
        Schema::table('academy_payments', function (Blueprint $table): void {
            $table->string('provider', 32)->nullable()->after('method');
            $table->unsignedBigInteger('gross_amount_minor')->nullable()->after('amount_minor');
            $table->unsignedBigInteger('customer_fee_minor')->default(0)->after('gross_amount_minor');
            $table->unsignedBigInteger('provider_fee_minor')->nullable()->after('customer_fee_minor');
            $table->unsignedBigInteger('unallocated_minor')->default(0)->after('provider_fee_minor');
        });
        Schema::create('online_payment_intents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academy_id');
            $table->foreignId('application_id');
            $table->foreignId('academy_payment_id')->nullable();
            $table->string('provider', 32);
            $table->string('reference', 80)->unique();
            $table->string('selection_key', 80);
            $table->string('status', 24)->default('pending');
            $table->char('currency', 3);
            $table->unsignedBigInteger('base_amount_minor');
            $table->unsignedBigInteger('surcharge_minor');
            $table->unsignedBigInteger('charged_amount_minor');
            $table->unsignedBigInteger('provider_fee_minor')->nullable();
            $table->json('allocation_plan');
            $table->string('access_code', 160)->nullable();
            $table->string('failure_reason', 500)->nullable();
            $table->timestamp('initialized_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('receipt_queued_at')->nullable();
            $table->timestamps();
            $table->unique(['academy_id', 'id']);
            $table->index(['academy_id', 'application_id', 'status']);
            $table->foreign('academy_id')->references('id')->on('academies')->cascadeOnDelete();
            $table->foreign(['academy_id', 'application_id'], 'online_intent_application_fk')
                ->references(['academy_id', 'id'])->on('applications')->restrictOnDelete();
            $table->foreign(['academy_id', 'academy_payment_id'], 'online_intent_payment_fk')
                ->references(['academy_id', 'id'])->on('academy_payments')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('online_payment_intents');
        Schema::table('academy_payments', fn (Blueprint $table) => $table->dropColumn(['provider', 'gross_amount_minor', 'customer_fee_minor', 'provider_fee_minor', 'unallocated_minor']));
        Schema::table('applications', function (Blueprint $table): void {
            $table->dropUnique(['public_access_token_hash']);
            $table->dropColumn(['email', 'public_access_token_hash']);
        });
        Schema::table('academies', fn (Blueprint $table) => $table->dropColumn(['paystack_fee_basis_points', 'paystack_fee_fixed_minor', 'paystack_fee_cap_minor', 'paystack_fee_waiver_threshold_minor']));
    }
};
