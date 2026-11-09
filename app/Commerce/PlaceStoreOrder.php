<?php

namespace App\Commerce;

use App\Models\Academy;
use App\Models\Branch;
use App\Models\InventoryStock;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\StoreOrder;
use App\Models\StoreOrderItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PlaceStoreOrder
{
    /** @param array<string, mixed> $data
     * @return array{order: StoreOrder, token: string}
     */
    public function handle(Academy $academy, array $data, ?User $actor = null, string $source = 'storefront'): array
    {
        return DB::transaction(function () use ($academy, $data, $actor, $source): array {
            $branchId = isset($data['branch_id']) ? (int) $data['branch_id'] : null;
            $lines = [];
            $subtotal = 0;

            foreach ($data['items'] as $line) {
                $variant = ProductVariant::query()->where('academy_id', $academy->id)
                    ->where('is_active', true)->with('product')->lockForUpdate()->find((int) $line['variant_id']);
                if (! $variant || $variant->product->status !== 'active') {
                    throw ValidationException::withMessages(['items' => 'A selected product is no longer available.']);
                }
                $quantity = (int) $line['quantity'];
                $price = $variant->price_minor ?? $variant->product->base_price_minor;
                $subtotal += $price * $quantity;
                $lines[] = compact('variant', 'quantity', 'price');
            }

            $token = Str::random(48);
            $order = StoreOrder::query()->create([
                'academy_id' => $academy->id,
                'branch_id' => $branchId,
                'number' => $this->number($academy),
                'source' => $source,
                'customer_name' => $data['customer_name'],
                'customer_email' => $data['customer_email'] ?? null,
                'customer_phone' => $data['customer_phone'] ?? null,
                'member_name' => $data['member_name'] ?? null,
                'delivery_address' => $data['delivery_address'] ?? null,
                'delivery_method' => $data['delivery_method'] ?? 'pickup',
                'currency' => $academy->currency,
                'subtotal_minor' => $subtotal,
                'total_minor' => $subtotal,
                'payment_status' => 'unpaid',
                'payment_preference' => $data['payment_option'] ?? 'pay_later',
                'fulfillment_status' => 'unfulfilled',
                'notes' => $data['notes'] ?? null,
                'public_token_hash' => hash('sha256', $token),
                'placed_at' => now(),
                'created_by_user_id' => $actor?->id,
            ]);

            foreach ($lines as $line) {
                $variant = $line['variant'];
                $item = $order->items()->create([
                    'academy_id' => $academy->id, 'product_id' => $variant->product_id, 'product_variant_id' => $variant->id,
                    'product_name' => $variant->product->name, 'variant_name' => $variant->name, 'sku' => $variant->sku,
                    'unit_price_minor' => $line['price'], 'quantity' => $line['quantity'], 'line_total_minor' => $line['price'] * $line['quantity'],
                ]);
                if ($variant->product->track_inventory) {
                    // The order branch is the customer's collection/service preference only.
                    // Inventory is always reserved from the academy's combined branch stock.
                    $this->reserveAcrossBranches($academy, $item, $variant, $line['quantity'], null, $actor);
                }
            }

            return ['order' => $order->load('items.allocations'), 'token' => $token];
        }, 3);
    }

    private function reserveAcrossBranches(Academy $academy, StoreOrderItem $item, ProductVariant $variant, int $quantity, ?int $preferredBranchId, ?User $actor): void
    {
        $query = InventoryStock::query()->where('academy_id', $academy->id)->where('product_variant_id', $variant->id)->whereNotNull('branch_id');
        if ($preferredBranchId !== null) {
            $query->orderByRaw('CASE WHEN branch_id = ? THEN 0 ELSE 1 END', [$preferredBranchId]);
        }
        $stocks = $query->orderBy('branch_id')->lockForUpdate()->get();
        $available = (int) $stocks->sum(fn (InventoryStock $stock): int => max(0, $stock->on_hand - $stock->reserved));
        if (! $variant->product->allow_backorder && $available < $quantity) {
            throw ValidationException::withMessages(['items' => "{$variant->product->name} ({$variant->name}) has insufficient combined branch stock."]);
        }

        $remaining = $quantity;
        $allocations = [];
        foreach ($stocks as $stock) {
            $allocate = min(max(0, $stock->on_hand - $stock->reserved), $remaining);
            if ($allocate < 1) {
                continue;
            }
            $stock->increment('reserved', $allocate);
            $allocations[$stock->branch_id] = ($allocations[$stock->branch_id] ?? 0) + $allocate;
            $remaining -= $allocate;
            if ($remaining === 0) {
                break;
            }
        }

        if ($remaining > 0) {
            $target = $preferredBranchId !== null ? $stocks->firstWhere('branch_id', $preferredBranchId) : $stocks->first();
            if ($target === null) {
                $branchId = $preferredBranchId ?? Branch::query()->where('academy_id', $academy->id)->where('is_active', true)->orderBy('id')->value('id');
                if ($branchId === null) {
                    throw ValidationException::withMessages(['items' => 'Create an active branch before accepting inventory orders.']);
                }
                InventoryStock::query()->firstOrCreate(
                    ['academy_id' => $academy->id, 'product_variant_id' => $variant->id, 'branch_scope_id' => $branchId],
                    ['branch_id' => $branchId, 'on_hand' => 0, 'reserved' => 0],
                );
                $target = InventoryStock::query()->where('academy_id', $academy->id)->where('product_variant_id', $variant->id)
                    ->where('branch_id', $branchId)->lockForUpdate()->sole();
            }
            $target->increment('reserved', $remaining);
            $allocations[$target->branch_id] = ($allocations[$target->branch_id] ?? 0) + $remaining;
        }

        foreach ($allocations as $branchId => $allocatedQuantity) {
            $item->allocations()->create(['academy_id' => $academy->id, 'branch_id' => $branchId, 'quantity' => $allocatedQuantity]);
            StockMovement::query()->create([
                'academy_id' => $academy->id, 'product_variant_id' => $variant->id, 'branch_id' => $branchId,
                'type' => 'reservation', 'reserved_delta' => $allocatedQuantity, 'reference_type' => $item->getMorphClass(),
                'reference_id' => $item->id, 'created_by_user_id' => $actor?->id, 'occurred_at' => now(),
            ]);
        }
    }

    private function number(Academy $academy): string
    {
        do {
            $number = 'ORD-'.now()->format('ymd').'-'.Str::upper(Str::random(6));
        } while (StoreOrder::query()->where('academy_id', $academy->id)->where('number', $number)->exists());

        return $number;
    }
}
