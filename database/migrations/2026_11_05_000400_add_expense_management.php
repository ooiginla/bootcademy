<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academy_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['academy_id', 'id']);
            $table->unique(['academy_id', 'name']);
            $table->index(['academy_id', 'is_active']);
        });

        Schema::table('academy_expenses', function (Blueprint $table): void {
            $table->foreignId('expense_category_id')->nullable()->after('academy_invoice_write_off_id');
            $table->foreignId('branch_id')->nullable()->after('expense_category_id');
            $table->string('vendor', 160)->nullable()->after('description');
            $table->string('reference', 120)->nullable()->after('vendor');

            $table->unique(['academy_id', 'id']);
            $table->index(['academy_id', 'expense_category_id', 'incurred_on'], 'expenses_category_date_index');
            $table->index(['academy_id', 'branch_id', 'incurred_on'], 'expenses_branch_date_index');
            $table->foreign(['academy_id', 'expense_category_id'], 'expense_category_fk')
                ->references(['academy_id', 'id'])->on('expense_categories')->restrictOnDelete();
            $table->foreign(['academy_id', 'branch_id'], 'expense_branch_fk')
                ->references(['academy_id', 'id'])->on('branches')->restrictOnDelete();
        });

        Schema::create('expense_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academy_id');
            $table->foreignId('academy_expense_id');
            $table->string('path', 500);
            $table->string('original_name', 255);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['academy_id', 'academy_expense_id']);
            $table->foreign('academy_id')->references('id')->on('academies')->cascadeOnDelete();
            $table->foreign(['academy_id', 'academy_expense_id'], 'expense_attachment_expense_fk')
                ->references(['academy_id', 'id'])->on('academy_expenses')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_attachments');
        Schema::table('academy_expenses', function (Blueprint $table): void {
            $table->dropForeign('expense_category_fk');
            $table->dropForeign('expense_branch_fk');
            $table->dropIndex('expenses_category_date_index');
            $table->dropIndex('expenses_branch_date_index');
            $table->dropUnique(['academy_id', 'id']);
            $table->dropColumn(['expense_category_id', 'branch_id', 'vendor', 'reference']);
        });
        Schema::dropIfExists('expense_categories');
    }
};
