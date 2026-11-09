<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_order_items', function (Blueprint $table): void {
            $table->unique(['academy_id', 'id']);
        });

        Schema::create('store_order_item_stock_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academy_id');
            $table->foreignId('store_order_item_id');
            $table->foreignId('branch_id');
            $table->unsignedInteger('quantity');
            $table->timestamps();
            $table->unique(['store_order_item_id', 'branch_id'], 'order_item_branch_allocation_unique');
            $table->foreign('academy_id')->references('id')->on('academies')->cascadeOnDelete();
            $table->foreign(['academy_id', 'store_order_item_id'], 'allocation_order_item_fk')->references(['academy_id', 'id'])->on('store_order_items')->cascadeOnDelete();
            $table->foreign(['academy_id', 'branch_id'], 'allocation_branch_fk')->references(['academy_id', 'id'])->on('branches')->restrictOnDelete();
        });

        DB::table('inventory_stocks')->whereNull('branch_id')->orderBy('id')->get()->each(function (object $stock): void {
            $branchId = DB::table('branches')->where('academy_id', $stock->academy_id)->orderBy('id')->value('id');
            if ($branchId === null) {
                return;
            }
            $destination = DB::table('inventory_stocks')->where('academy_id', $stock->academy_id)
                ->where('product_variant_id', $stock->product_variant_id)->where('branch_scope_id', $branchId)->first();
            if ($destination !== null) {
                DB::table('inventory_stocks')->where('id', $destination->id)->update([
                    'on_hand' => $destination->on_hand + $stock->on_hand,
                    'reserved' => $destination->reserved + $stock->reserved,
                    'updated_at' => now(),
                ]);
                DB::table('inventory_stocks')->where('id', $stock->id)->delete();
            } else {
                DB::table('inventory_stocks')->where('id', $stock->id)->update([
                    'branch_id' => $branchId,
                    'branch_scope_id' => $branchId,
                    'updated_at' => now(),
                ]);
            }
        });

        DB::table('store_order_items')->join('store_orders', 'store_orders.id', '=', 'store_order_items.store_order_id')
            ->select('store_order_items.id', 'store_order_items.academy_id', 'store_order_items.quantity', 'store_orders.branch_id')
            ->orderBy('store_order_items.id')->get()->each(function (object $item): void {
                $branchId = $item->branch_id ?? DB::table('branches')->where('academy_id', $item->academy_id)->orderBy('id')->value('id');
                if ($branchId !== null) {
                    DB::table('store_order_item_stock_allocations')->insert([
                        'academy_id' => $item->academy_id, 'store_order_item_id' => $item->id, 'branch_id' => $branchId,
                        'quantity' => $item->quantity, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_order_item_stock_allocations');
        Schema::table('store_order_items', function (Blueprint $table): void {
            $table->dropUnique(['academy_id', 'id']);
        });
    }
};
