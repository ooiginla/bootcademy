<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_vendors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academy_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('address', 255)->nullable();
            $table->timestamps();

            $table->unique(['academy_id', 'id']);
            $table->unique(['academy_id', 'name']);
        });

        Schema::table('academy_expenses', function (Blueprint $table): void {
            $table->foreignId('expense_vendor_id')->nullable()->after('expense_category_id');
            $table->index(['academy_id', 'expense_vendor_id', 'incurred_on'], 'expenses_vendor_date_index');
            $table->foreign(['academy_id', 'expense_vendor_id'], 'expense_vendor_fk')
                ->references(['academy_id', 'id'])->on('expense_vendors')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('academy_expenses', function (Blueprint $table): void {
            $table->dropForeign('expense_vendor_fk');
            $table->dropIndex('expenses_vendor_date_index');
            $table->dropColumn('expense_vendor_id');
        });
        Schema::dropIfExists('expense_vendors');
    }
};
