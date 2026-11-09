<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academy_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('slug', 120);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['academy_id', 'id']);
            $table->unique(['academy_id', 'slug']);
        });

        Schema::create('product_tags', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academy_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('slug', 100);
            $table->timestamps();
            $table->unique(['academy_id', 'id']);
            $table->unique(['academy_id', 'slug']);
        });

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academy_id');
            $table->foreignId('product_category_id')->nullable();
            $table->string('name', 160);
            $table->string('slug', 180);
            $table->string('sku', 80)->nullable();
            $table->text('description')->nullable();
            $table->char('currency', 3);
            $table->unsignedBigInteger('base_price_minor');
            $table->string('status', 20)->default('draft');
            $table->boolean('track_inventory')->default(true);
            $table->boolean('allow_backorder')->default(false);
            $table->string('image_path', 500)->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['academy_id', 'id']);
            $table->unique(['academy_id', 'slug']);
            $table->unique(['academy_id', 'sku']);
            $table->index(['academy_id', 'status', 'product_category_id']);
            $table->foreign('academy_id')->references('id')->on('academies')->cascadeOnDelete();
            $table->foreign(['academy_id', 'product_category_id'], 'product_category_fk')->references(['academy_id', 'id'])->on('product_categories')->restrictOnDelete();
        });

        Schema::create('product_product_tag', function (Blueprint $table): void {
            $table->foreignId('academy_id');
            $table->foreignId('product_id');
            $table->foreignId('product_tag_id');
            $table->primary(['product_id', 'product_tag_id']);
            $table->foreign(['academy_id', 'product_id'], 'product_tag_product_fk')->references(['academy_id', 'id'])->on('products')->cascadeOnDelete();
            $table->foreign(['academy_id', 'product_tag_id'], 'product_tag_tag_fk')->references(['academy_id', 'id'])->on('product_tags')->cascadeOnDelete();
        });

        Schema::create('product_variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academy_id');
            $table->foreignId('product_id');
            $table->string('name', 120)->default('Default');
            $table->string('sku', 80)->nullable();
            $table->json('attributes')->nullable();
            $table->unsignedBigInteger('price_minor')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['academy_id', 'id']);
            $table->unique(['academy_id', 'sku']);
            $table->index(['academy_id', 'product_id', 'is_active']);
            $table->foreign('academy_id')->references('id')->on('academies')->cascadeOnDelete();
            $table->foreign(['academy_id', 'product_id'], 'variant_product_fk')->references(['academy_id', 'id'])->on('products')->cascadeOnDelete();
        });

        Schema::create('inventory_stocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academy_id');
            $table->foreignId('product_variant_id');
            $table->foreignId('branch_id')->nullable();
            $table->unsignedBigInteger('branch_scope_id')->default(0);
            $table->unsignedInteger('on_hand')->default(0);
            $table->unsignedInteger('reserved')->default(0);
            $table->timestamps();
            $table->unique(['academy_id', 'product_variant_id', 'branch_scope_id'], 'stock_scope_unique');
            $table->foreign('academy_id')->references('id')->on('academies')->cascadeOnDelete();
            $table->foreign(['academy_id', 'product_variant_id'], 'stock_variant_fk')->references(['academy_id', 'id'])->on('product_variants')->cascadeOnDelete();
            $table->foreign(['academy_id', 'branch_id'], 'stock_branch_fk')->references(['academy_id', 'id'])->on('branches')->restrictOnDelete();
        });

        Schema::create('store_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academy_id');
            $table->foreignId('branch_id')->nullable();
            $table->string('number', 40);
            $table->string('source', 20)->default('storefront');
            $table->string('customer_name', 160);
            $table->string('customer_email', 254)->nullable();
            $table->string('customer_phone', 40)->nullable();
            $table->text('delivery_address')->nullable();
            $table->string('delivery_method', 20)->default('pickup');
            $table->char('currency', 3);
            $table->unsignedBigInteger('subtotal_minor');
            $table->unsignedBigInteger('discount_minor')->default(0);
            $table->unsignedBigInteger('delivery_minor')->default(0);
            $table->unsignedBigInteger('total_minor');
            $table->string('payment_status', 24)->default('unpaid');
            $table->string('fulfillment_status', 24)->default('unfulfilled');
            $table->text('notes')->nullable();
            $table->char('public_token_hash', 64)->nullable()->unique();
            $table->timestamp('placed_at');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['academy_id', 'id']);
            $table->unique(['academy_id', 'number']);
            $table->index(['academy_id', 'payment_status', 'placed_at']);
            $table->index(['academy_id', 'fulfillment_status', 'placed_at']);
            $table->foreign('academy_id')->references('id')->on('academies')->cascadeOnDelete();
            $table->foreign(['academy_id', 'branch_id'], 'store_order_branch_fk')->references(['academy_id', 'id'])->on('branches')->restrictOnDelete();
        });

        Schema::create('store_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academy_id');
            $table->foreignId('store_order_id');
            $table->foreignId('product_id');
            $table->foreignId('product_variant_id');
            $table->string('product_name', 160);
            $table->string('variant_name', 120);
            $table->string('sku', 80)->nullable();
            $table->unsignedBigInteger('unit_price_minor');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('line_total_minor');
            $table->timestamps();
            $table->foreign('academy_id')->references('id')->on('academies')->cascadeOnDelete();
            $table->foreign(['academy_id', 'store_order_id'], 'order_item_order_fk')->references(['academy_id', 'id'])->on('store_orders')->cascadeOnDelete();
            $table->foreign(['academy_id', 'product_id'], 'order_item_product_fk')->references(['academy_id', 'id'])->on('products')->restrictOnDelete();
            $table->foreign(['academy_id', 'product_variant_id'], 'order_item_variant_fk')->references(['academy_id', 'id'])->on('product_variants')->restrictOnDelete();
        });

        Schema::create('store_order_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academy_id');
            $table->foreignId('store_order_id');
            $table->string('reference', 80);
            $table->string('method', 30);
            $table->string('status', 20)->default('verified');
            $table->char('currency', 3);
            $table->unsignedBigInteger('amount_minor');
            $table->date('received_on');
            $table->string('external_reference', 120)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['academy_id', 'reference']);
            $table->foreign('academy_id')->references('id')->on('academies')->cascadeOnDelete();
            $table->foreign(['academy_id', 'store_order_id'], 'store_payment_order_fk')->references(['academy_id', 'id'])->on('store_orders')->restrictOnDelete();
        });

        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academy_id');
            $table->foreignId('product_variant_id');
            $table->foreignId('branch_id')->nullable();
            $table->string('type', 30);
            $table->integer('on_hand_delta')->default(0);
            $table->integer('reserved_delta')->default(0);
            $table->nullableMorphs('reference');
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['academy_id', 'product_variant_id', 'occurred_at'], 'movement_variant_date_index');
            $table->foreign('academy_id')->references('id')->on('academies')->cascadeOnDelete();
            $table->foreign(['academy_id', 'product_variant_id'], 'movement_variant_fk')->references(['academy_id', 'id'])->on('product_variants')->restrictOnDelete();
            $table->foreign(['academy_id', 'branch_id'], 'movement_branch_fk')->references(['academy_id', 'id'])->on('branches')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('store_order_payments');
        Schema::dropIfExists('store_order_items');
        Schema::dropIfExists('store_orders');
        Schema::dropIfExists('inventory_stocks');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('product_product_tag');
        Schema::dropIfExists('products');
        Schema::dropIfExists('product_tags');
        Schema::dropIfExists('product_categories');
    }
};
