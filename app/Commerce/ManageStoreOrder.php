<?php

namespace App\Commerce;

use App\Models\InventoryStock;
use App\Models\StockMovement;
use App\Models\StoreOrder;
use App\Models\StoreOrderPayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ManageStoreOrder
{
    public function recordPayment(StoreOrder $order, int $amountMinor, string $method, string $receivedOn, ?string $externalReference, ?string $notes, User $actor): StoreOrderPayment
    {
        return DB::transaction(function () use ($order, $amountMinor, $method, $receivedOn, $externalReference, $notes, $actor): StoreOrderPayment {
            $order = StoreOrder::query()->lockForUpdate()->findOrFail($order->id);
            if ($order->cancelled_at) {
                throw ValidationException::withMessages(['payment' => 'A cancelled order cannot receive payment.']);
            }
            $paid = (int) $order->payments()->where('status', 'verified')->sum('amount_minor');
            if ($amountMinor > ($order->total_minor - $paid)) {
                throw ValidationException::withMessages(['amount' => 'Payment cannot exceed the outstanding order balance.']);
            }
            $payment = $order->payments()->create([
                'academy_id' => $order->academy_id, 'reference' => 'SP-'.Str::upper(Str::random(12)), 'method' => $method,
                'status' => 'verified', 'currency' => $order->currency, 'amount_minor' => $amountMinor,
                'charged_amount_minor' => $amountMinor,
                'received_on' => $receivedOn, 'external_reference' => $externalReference, 'notes' => $notes, 'recorded_by_user_id' => $actor->id,
            ]);
            $newPaid = $paid + $amountMinor;
            $order->update(['payment_status' => $newPaid >= $order->total_minor ? 'paid' : 'partially_paid', 'paid_at' => $newPaid >= $order->total_minor ? now() : null]);

            return $payment;
        }, 3);
    }

    public function updateFulfillment(StoreOrder $order, string $status, User $actor): void
    {
        DB::transaction(function () use ($order, $status, $actor): void {
            $order = StoreOrder::query()->with(['items.variant.product', 'items.allocations'])->lockForUpdate()->findOrFail($order->id);
            if ($order->cancelled_at) {
                throw ValidationException::withMessages(['status' => 'A cancelled order cannot be fulfilled.']);
            }
            if ($order->fulfilled_at && $status !== 'delivered') {
                throw ValidationException::withMessages(['status' => 'A delivered order cannot be moved backwards.']);
            }
            if ($status === 'delivered' && ! $order->fulfilled_at) {
                foreach ($order->items as $item) {
                    if (! $item->variant->product->track_inventory) {
                        continue;
                    }
                    if ($item->allocations->isEmpty()) {
                        throw ValidationException::withMessages(['status' => 'This order has no branch stock allocation.']);
                    }
                    foreach ($item->allocations->sortBy('branch_id') as $allocation) {
                        $stock = InventoryStock::query()->where('academy_id', $order->academy_id)->where('product_variant_id', $item->product_variant_id)
                            ->where('branch_id', $allocation->branch_id)->lockForUpdate()->sole();
                        if ($stock->reserved < $allocation->quantity || $stock->on_hand < $allocation->quantity) {
                            throw ValidationException::withMessages(['status' => 'Reserved branch stock is not yet sufficient to fulfil this order.']);
                        }
                        $stock->decrement('on_hand', $allocation->quantity);
                        $stock->decrement('reserved', $allocation->quantity);
                        StockMovement::query()->create(['academy_id' => $order->academy_id, 'product_variant_id' => $item->product_variant_id, 'branch_id' => $allocation->branch_id,
                            'type' => 'sale', 'on_hand_delta' => -$allocation->quantity, 'reserved_delta' => -$allocation->quantity,
                            'reference_type' => $item->getMorphClass(), 'reference_id' => $item->id, 'created_by_user_id' => $actor->id, 'occurred_at' => now()]);
                    }
                }
            }
            $order->update(['fulfillment_status' => $status, 'fulfilled_at' => $status === 'delivered' ? now() : $order->fulfilled_at]);
        }, 3);
    }

    public function cancel(StoreOrder $order, User $actor): void
    {
        DB::transaction(function () use ($order, $actor): void {
            $order = StoreOrder::query()->with(['items.variant.product', 'items.allocations'])->lockForUpdate()->findOrFail($order->id);
            if ($order->cancelled_at) {
                return;
            }
            if ($order->fulfilled_at) {
                throw ValidationException::withMessages(['order' => 'A delivered order cannot be cancelled.']);
            }
            if ($order->payment_status !== 'unpaid') {
                throw ValidationException::withMessages(['order' => 'Record a refund before cancelling a paid order.']);
            }
            foreach ($order->items as $item) {
                if (! $item->variant->product->track_inventory) {
                    continue;
                }
                foreach ($item->allocations->sortBy('branch_id') as $allocation) {
                    $stock = InventoryStock::query()->where('academy_id', $order->academy_id)->where('product_variant_id', $item->product_variant_id)
                        ->where('branch_id', $allocation->branch_id)->lockForUpdate()->sole();
                    if ($stock->reserved < $allocation->quantity) {
                        throw ValidationException::withMessages(['order' => 'Reserved branch stock is inconsistent and cannot be released safely.']);
                    }
                    $stock->decrement('reserved', $allocation->quantity);
                    StockMovement::query()->create(['academy_id' => $order->academy_id, 'product_variant_id' => $item->product_variant_id, 'branch_id' => $allocation->branch_id,
                        'type' => 'release', 'reserved_delta' => -$allocation->quantity, 'reference_type' => $item->getMorphClass(), 'reference_id' => $item->id,
                        'created_by_user_id' => $actor->id, 'occurred_at' => now()]);
                }
            }
            $order->update(['fulfillment_status' => 'cancelled', 'cancelled_at' => now()]);
        }, 3);
    }
}
