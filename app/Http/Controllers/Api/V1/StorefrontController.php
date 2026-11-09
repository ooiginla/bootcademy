<?php

namespace App\Http\Controllers\Api\V1;

use App\Commerce\PlaceStoreOrder;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Platform\AuditLogger;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StorefrontController extends Controller
{
    public function catalog(Request $request): JsonResponse
    {
        $academy = $request->attributes->get('api_academy');

        return response()->json(['data' => $academy->products()->where('status', 'active')->with(['category', 'tags', 'variants.stocks'])->orderBy('name')->get()->map(fn (Product $product) => [
            'id' => $product->id, 'name' => $product->name, 'slug' => $product->slug, 'description' => $product->description, 'category' => $product->category?->name,
            'tags' => $product->tags->pluck('name'), 'currency' => $product->currency, 'price' => Money::fromMinor($product->base_price_minor),
            'variants' => $product->variants->where('is_active', true)->values()->map(fn ($variant) => ['id' => $variant->id, 'name' => $variant->name,
                'price' => Money::fromMinor($variant->price_minor ?? $product->base_price_minor), 'attributes' => $variant->attributes ?? [],
                'available' => $product->track_inventory ? max(0, $variant->stocks->whereNotNull('branch_id')->sum('on_hand') - $variant->stocks->whereNotNull('branch_id')->sum('reserved')) : null]),
        ])]);
    }

    public function order(Request $request, PlaceStoreOrder $place, AuditLogger $audit): JsonResponse
    {
        $academy = $request->attributes->get('api_academy');
        $data = $request->validate(['customer_name' => ['required', 'string', 'max:160'], 'customer_email' => ['nullable', 'email:rfc', 'max:254'], 'customer_phone' => ['nullable', 'string', 'max:40'],
            'branch_id' => ['nullable', 'integer'], 'delivery_method' => ['required', 'in:pickup,delivery'], 'delivery_address' => ['nullable', 'required_if:delivery_method,delivery', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:50'], 'items.*.variant_id' => ['required', 'integer'], 'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100']]);
        if (isset($data['branch_id']) && ! $academy->branches()->whereKey($data['branch_id'])->where('is_active', true)->exists()) {
            abort(422, 'The selected branch is invalid.');
        }
        $result = $place->handle($academy, $data, null, 'api');
        $audit->record('academy.store.order.created', null, $academy, $result['order'], ['source' => 'api'], $request);

        return response()->json(['data' => ['number' => $result['order']->number, 'currency' => $result['order']->currency, 'total' => Money::fromMinor($result['order']->total_minor),
            'payment_status' => $result['order']->payment_status, 'fulfillment_status' => $result['order']->fulfillment_status, 'status_token' => $result['token']]], 201);
    }
}
