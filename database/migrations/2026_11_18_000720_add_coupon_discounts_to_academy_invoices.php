<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('academy_invoices', 'coupon_discount_minor')) {
            Schema::table('academy_invoices', function (Blueprint $table): void {
                $table->unsignedBigInteger('coupon_discount_minor')->default(0)->after('scholarship_concession_minor');
            });
        }
        if (! Schema::hasColumn('academy_invoice_installments', 'coupon_discount_minor')) {
            Schema::table('academy_invoice_installments', function (Blueprint $table): void {
                $table->unsignedBigInteger('coupon_discount_minor')->default(0)->after('scholarship_concession_minor');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('academy_invoice_installments', 'coupon_discount_minor')) {
            Schema::table('academy_invoice_installments', function (Blueprint $table): void {
                $table->dropColumn('coupon_discount_minor');
            });
        }
        if (Schema::hasColumn('academy_invoices', 'coupon_discount_minor')) {
            Schema::table('academy_invoices', function (Blueprint $table): void {
                $table->dropColumn('coupon_discount_minor');
            });
        }
    }
};
