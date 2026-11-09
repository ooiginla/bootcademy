<?php

namespace App\Http\Controllers\Academies;

use App\Academies\AcademyContext;
use App\Commerce\ManageStoreOrder;
use App\Commerce\PlaceStoreOrder;
use App\Http\Controllers\Controller;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductTag;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\StoreOrder;
use App\Platform\AuditLogger;
use App\Support\Money;
use Illuminate\Database\Query\Builder;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StoreController extends Controller
{
    public function index(Request $request, AcademyContext $context): Response
    {
        $this->authorizeAccess($context);
        $academy = $context->academy();
        $products = $academy->products()->with(['category', 'tags', 'variants.stocks.branch'])->orderBy('name')->get();
        $orders = $academy->storeOrders()->with(['branch', 'items', 'payments'])->orderByDesc('placed_at')->limit(250)->get();

        return Inertia::render('academies/store', [
            'currency' => $academy->currency,
            'can_manage_catalog' => $context->canManageAcademy(),
            'products' => $products->map(fn (Product $product): array => [
                'id' => $product->id, 'name' => $product->name, 'slug' => $product->slug, 'sku' => $product->sku,
                'description' => $product->description, 'status' => $product->status, 'currency' => $product->currency,
                'price' => Money::fromMinor($product->base_price_minor), 'category_id' => $product->product_category_id,
                'category' => $product->category?->name, 'track_inventory' => $product->track_inventory,
                'allow_backorder' => $product->allow_backorder, 'tag_ids' => $product->tags->pluck('id'),
                'inventory_total' => $product->variants->flatMap->stocks->whereNotNull('branch_id')->sum('on_hand'),
                'image_url' => $product->image_path ? '/app/store/products/'.$product->id.'/image?v='.$product->updated_at->timestamp : null,
                'variants' => $product->variants->map(fn (ProductVariant $variant): array => [
                    'id' => $variant->id, 'name' => $variant->name, 'sku' => $variant->sku, 'attributes' => $variant->attributes ?? [],
                    'price' => $variant->price_minor === null ? null : Money::fromMinor($variant->price_minor), 'is_active' => $variant->is_active,
                    'stocks' => $variant->stocks->whereNotNull('branch_id')
                        ->filter(fn (InventoryStock $stock): bool => $stock->on_hand !== 0 || $stock->reserved !== 0)
                        ->map(fn (InventoryStock $stock): array => ['branch_id' => $stock->branch_id, 'branch' => $stock->branch?->name, 'on_hand' => $stock->on_hand, 'reserved' => $stock->reserved, 'available' => $stock->on_hand - $stock->reserved])->values(),
                ]),
            ]),
            'categories' => $academy->productCategories()->withCount('products')->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'slug', 'description', 'is_active']),
            'tags' => $academy->productTags()->withCount('products')->orderBy('name')->get(['id', 'name', 'slug']),
            'branches' => $academy->branches()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'orders' => $orders->map(fn (StoreOrder $order): array => [
                'id' => $order->id, 'number' => $order->number, 'source' => $order->source, 'customer_name' => $order->customer_name,
                'customer_email' => $order->customer_email, 'customer_phone' => $order->customer_phone, 'member_name' => $order->member_name,
                'branch' => $order->branch?->name ?? 'No collection location selected',
                'currency' => $order->currency, 'total' => Money::fromMinor($order->total_minor),
                'paid' => Money::fromMinor((int) $order->payments->where('status', 'verified')->sum('amount_minor')),
                'payment_status' => $order->payment_status, 'payment_preference' => $order->payment_preference,
                'fulfillment_status' => $order->fulfillment_status,
                'placed_at' => $order->placed_at->toIso8601String(),
                'items' => $order->items->map(fn ($item): array => ['name' => $item->product_name, 'variant' => $item->variant_name, 'quantity' => $item->quantity, 'unit_price' => Money::fromMinor($item->unit_price_minor)]),
            ]),
            'summary' => ['products' => $products->count(), 'stock_units' => $products->flatMap->variants->flatMap->stocks->whereNotNull('branch_id')->sum('on_hand'),
                'unpaid_orders' => $orders->whereIn('payment_status', ['unpaid', 'partially_paid'])->count(),
                'sales' => Money::fromMinor((int) $orders->flatMap->payments->where('status', 'verified')->sum('amount_minor'))],
            'active_tab' => in_array($request->query('tab'), ['products', 'inventory', 'orders', 'categories'], true) ? $request->query('tab') : 'products',
        ]);
    }

    public function storeProduct(Request $request, AcademyContext $context, AuditLogger $audit): RedirectResponse
    {
        abort_unless($context->canManageAcademy(), 403);
        $data = $this->productData($request, $context);
        $image = $data['image'] ?? null;
        unset($data['image']);
        $product = DB::transaction(function () use ($data, $context, $request): Product {
            $tagIds = $data['tag_ids'] ?? [];
            unset($data['tag_ids']);
            $variantName = $data['variant_name'] ?? 'Default';
            $variantSku = $data['variant_sku'] ?? $data['sku'];
            unset($data['variant_name'], $data['variant_sku']);
            $data['base_price_minor'] = Money::toMinor($data['price']);
            unset($data['price']);
            $data['academy_id'] = $context->id();
            $data['currency'] = $context->academy()->currency;
            $data['created_by_user_id'] = $request->user()->id;
            $data['slug'] = $this->uniqueSlug($context->id(), $data['name']);
            $product = Product::query()->create($data);
            $product->variants()->create(['academy_id' => $context->id(), 'name' => $variantName, 'sku' => $variantSku, 'is_active' => true]);
            $product->tags()->sync(collect($tagIds)->mapWithKeys(fn ($id) => [$id => ['academy_id' => $context->id()]])->all());

            return $product;
        });
        if ($image instanceof UploadedFile) {
            $this->persistProductImage($product, $image, $context->id());
        }
        $audit->record('academy.store.product.created', $request->user(), $context->academy(), $product, [], $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Product created. Add stock before publishing it for sale.']);

        return back();
    }

    public function updateProduct(Request $request, Product $product, AcademyContext $context, AuditLogger $audit): RedirectResponse
    {
        abort_unless($context->canManageAcademy(), 403);
        $this->tenant($product, $context);
        $data = $this->productData($request, $context, $product->id);
        $image = $data['image'] ?? null;
        unset($data['image']);
        $before = $product->only(['name', 'status', 'base_price_minor']);
        $tagIds = $data['tag_ids'] ?? [];
        unset($data['tag_ids'], $data['variant_name'], $data['variant_sku']);
        $data['base_price_minor'] = Money::toMinor($data['price']);
        unset($data['price']);
        if ($data['name'] !== $product->name) {
            $data['slug'] = $this->uniqueSlug($context->id(), $data['name'], $product->id);
        }
        DB::transaction(function () use ($product, $data, $tagIds, $context): void {
            $product->update($data);
            $product->tags()->sync(collect($tagIds)->mapWithKeys(fn ($id) => [$id => ['academy_id' => $context->id()]])->all());
        });
        if ($image instanceof UploadedFile) {
            $this->persistProductImage($product, $image, $context->id());
        }
        $audit->record('academy.store.product.updated', $request->user(), $context->academy(), $product, ['before' => $before], $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Product updated.']);

        return back();
    }

    public function image(Product $product, AcademyContext $context): StreamedResponse
    {
        $this->authorizeAccess($context);
        $this->tenant($product, $context);
        abort_if($product->image_path === null, 404);
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('local');
        abort_unless($disk->exists($product->image_path), 404);

        return $disk->response($product->image_path, null, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function updateImage(Request $request, Product $product, AcademyContext $context, AuditLogger $audit): RedirectResponse
    {
        abort_unless($context->canManageAcademy(), 403);
        $this->tenant($product, $context);
        $data = $request->validate(['image' => ['required', File::image()->max(5 * 1024)]]);
        $this->persistProductImage($product, $data['image'], $context->id());
        $audit->record('academy.store.product.image-updated', $request->user(), $context->academy(), $product, [], $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Product image updated.']);

        return back();
    }

    public function storeVariant(Request $request, Product $product, AcademyContext $context, AuditLogger $audit): RedirectResponse
    {
        abort_unless($context->canManageAcademy(), 403);
        $this->tenant($product, $context);
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'sku' => ['nullable', 'string', 'max:80', Rule::unique('product_variants', 'sku')->where('academy_id', $context->id())],
            'price' => ['nullable', 'regex:/^\d{1,12}(?:\.\d{1,2})?$/'], 'attributes' => ['nullable', 'array'], 'attributes.*' => ['nullable', 'string', 'max:80']]);
        $variant = $product->variants()->create(['academy_id' => $context->id(), 'name' => $data['name'], 'sku' => $data['sku'] ?? null,
            'price_minor' => filled($data['price'] ?? null) ? Money::toMinor($data['price']) : null, 'attributes' => array_filter($data['attributes'] ?? []), 'is_active' => true]);
        $audit->record('academy.store.variant.created', $request->user(), $context->academy(), $variant, [], $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Product option added.']);

        return back();
    }

    public function adjustStock(Request $request, ProductVariant $variant, AcademyContext $context, AuditLogger $audit): RedirectResponse
    {
        $this->authorizeAccess($context);
        $this->tenant($variant, $context);
        $data = $request->validate(['branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->where(fn (Builder $q) => $q->where('academy_id', $context->id())->where('is_active', true))],
            'direction' => ['required', Rule::in(['add', 'remove'])], 'quantity' => ['required', 'integer', 'between:1,1000000'],
            'reason_code' => ['required', Rule::in(array_keys($this->adjustmentReasons()))],
            'reason_details' => ['nullable', 'required_if:reason_code,other', 'string', 'max:500']]);
        $signedQuantity = $data['direction'] === 'add' ? (int) $data['quantity'] : -(int) $data['quantity'];
        $reason = $this->adjustmentReasons()[$data['reason_code']].(filled($data['reason_details'] ?? null) ? ': '.trim($data['reason_details']) : '');
        DB::transaction(function () use ($data, $signedQuantity, $reason, $variant, $context, $request): void {
            $branch = (int) $data['branch_id'];
            $scope = $branch;
            InventoryStock::query()->firstOrCreate(['academy_id' => $context->id(), 'product_variant_id' => $variant->id, 'branch_scope_id' => $scope], ['branch_id' => $branch]);
            $stock = InventoryStock::query()->where('academy_id', $context->id())->where('product_variant_id', $variant->id)->where('branch_scope_id', $scope)->lockForUpdate()->sole();
            $newOnHand = $stock->on_hand + $signedQuantity;
            if ($newOnHand < $stock->reserved || $newOnHand < 0) {
                throw ValidationException::withMessages(['quantity' => 'This adjustment would reduce stock below reserved or zero units.']);
            }
            $stock->update(['on_hand' => $newOnHand]);
            StockMovement::query()->create(['academy_id' => $context->id(), 'product_variant_id' => $variant->id, 'branch_id' => $branch,
                'type' => $signedQuantity > 0 ? 'adjustment_in' : 'adjustment_out', 'on_hand_delta' => $signedQuantity,
                'notes' => $reason, 'created_by_user_id' => $request->user()->id, 'occurred_at' => now()]);
        }, 3);
        $audit->record('academy.store.stock.adjusted', $request->user(), $context->academy(), $variant, ['quantity' => $signedQuantity, 'branch_id' => $data['branch_id'] ?? null, 'reason_code' => $data['reason_code'], 'reason_details' => $data['reason_details'] ?? null], $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Stock adjustment recorded.']);

        return back();
    }

    public function transferStock(Request $request, ProductVariant $variant, AcademyContext $context, AuditLogger $audit): RedirectResponse
    {
        $this->authorizeAccess($context);
        $this->tenant($variant, $context);
        $branchRule = Rule::exists('branches', 'id')->where(fn (Builder $q) => $q->where('academy_id', $context->id()));
        $data = $request->validate([
            'from_branch_id' => ['required', 'integer', $branchRule],
            'to_branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->where(fn (Builder $q) => $q->where('academy_id', $context->id())->where('is_active', true))],
            'quantity' => ['required', 'integer', 'between:1,1000000'],
            'reason_code' => ['required', Rule::in(['branch_restock', 'event_allocation', 'stock_rebalancing', 'other'])],
            'reason_details' => ['nullable', 'required_if:reason_code,other', 'string', 'max:500'],
        ]);
        $fromScope = (int) $data['from_branch_id'];
        $toScope = (int) $data['to_branch_id'];
        if ($fromScope === $toScope) {
            throw ValidationException::withMessages(['to_branch_id' => 'Choose a different destination location.']);
        }
        $reference = 'TRF-'.Str::upper(Str::random(12));
        $reasonLabels = ['branch_restock' => 'Branch restock', 'event_allocation' => 'Event allocation', 'stock_rebalancing' => 'Stock rebalancing', 'other' => 'Other'];
        $notes = $reasonLabels[$data['reason_code']].(filled($data['reason_details'] ?? null) ? ': '.trim($data['reason_details']) : '');

        DB::transaction(function () use ($data, $variant, $context, $request, $fromScope, $toScope, $reference, $notes): void {
            foreach ([['scope' => $fromScope, 'branch' => $data['from_branch_id'] ?? null], ['scope' => $toScope, 'branch' => $data['to_branch_id'] ?? null]] as $location) {
                InventoryStock::query()->firstOrCreate(
                    ['academy_id' => $context->id(), 'product_variant_id' => $variant->id, 'branch_scope_id' => $location['scope']],
                    ['branch_id' => $location['branch'], 'on_hand' => 0, 'reserved' => 0],
                );
            }
            $stocks = InventoryStock::query()->where('academy_id', $context->id())->where('product_variant_id', $variant->id)
                ->whereIn('branch_scope_id', [$fromScope, $toScope])->orderBy('branch_scope_id')->lockForUpdate()->get()->keyBy('branch_scope_id');
            $source = $stocks->get($fromScope);
            $destination = $stocks->get($toScope);
            $quantity = (int) $data['quantity'];
            if ($source === null || ($source->on_hand - $source->reserved) < $quantity) {
                throw ValidationException::withMessages(['quantity' => 'The source location does not have enough unreserved stock for this transfer.']);
            }
            $source->decrement('on_hand', $quantity);
            $destination->increment('on_hand', $quantity);
            StockMovement::query()->create(['academy_id' => $context->id(), 'product_variant_id' => $variant->id, 'branch_id' => $data['from_branch_id'] ?? null,
                'type' => 'transfer_out', 'on_hand_delta' => -$quantity, 'transfer_reference' => $reference, 'notes' => $notes,
                'created_by_user_id' => $request->user()->id, 'occurred_at' => now()]);
            StockMovement::query()->create(['academy_id' => $context->id(), 'product_variant_id' => $variant->id, 'branch_id' => $data['to_branch_id'] ?? null,
                'type' => 'transfer_in', 'on_hand_delta' => $quantity, 'transfer_reference' => $reference, 'notes' => $notes,
                'created_by_user_id' => $request->user()->id, 'occurred_at' => now()]);
        }, 3);

        $audit->record('academy.store.stock.transferred', $request->user(), $context->academy(), $variant, [
            'reference' => $reference, 'quantity' => (int) $data['quantity'], 'from_branch_id' => $data['from_branch_id'] ?? null,
            'to_branch_id' => $data['to_branch_id'] ?? null, 'reason_code' => $data['reason_code'], 'reason_details' => $data['reason_details'] ?? null,
        ], $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => "Stock transfer {$reference} completed."]);

        return back();
    }

    public function storeCategory(Request $request, AcademyContext $context, AuditLogger $audit): RedirectResponse
    {
        abort_unless($context->canManageAcademy(), 403);
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'description' => ['nullable', 'string', 'max:1000']]);
        $category = $context->academy()->productCategories()->create($data + ['slug' => $this->categorySlug($context->id(), $data['name'])]);
        $audit->record('academy.store.category.created', $request->user(), $context->academy(), $category, [], $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Product category created.']);

        return back();
    }

    public function storeTag(Request $request, AcademyContext $context, AuditLogger $audit): RedirectResponse
    {
        abort_unless($context->canManageAcademy(), 403);
        $data = $request->validate(['name' => ['required', 'string', 'max:80']]);
        $tag = $context->academy()->productTags()->create(['name' => $data['name'], 'slug' => $this->tagSlug($context->id(), $data['name'])]);
        $audit->record('academy.store.tag.created', $request->user(), $context->academy(), $tag, [], $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Product tag created.']);

        return back();
    }

    public function storeOrder(Request $request, AcademyContext $context, PlaceStoreOrder $place, ManageStoreOrder $manage, AuditLogger $audit): RedirectResponse
    {
        $this->authorizeAccess($context);
        $data = $this->orderData($request, $context);
        $result = $place->handle($context->academy(), $data, $request->user(), 'admin');
        $order = $result['order'];
        if (filled($data['amount_paid'] ?? null)) {
            $manage->recordPayment($order, Money::toMinor($data['amount_paid']), $data['payment_method'], $data['received_on'], $data['payment_reference'] ?? null, null, $request->user());
        }
        if (($data['mark_delivered'] ?? false) === true) {
            $manage->updateFulfillment($order, 'delivered', $request->user());
        }
        $audit->record('academy.store.order.created', $request->user(), $context->academy(), $order, ['source' => 'admin'], $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => "Offline sale {$order->number} recorded."]);

        return back();
    }

    public function showOrder(StoreOrder $order, AcademyContext $context): Response
    {
        $this->authorizeAccess($context);
        $this->tenant($order, $context);
        $order->load([
            'branch',
            'items.allocations.branch',
            'payments' => fn ($query) => $query->with('recordedBy')->orderByDesc('created_at'),
        ]);

        $verifiedPaidMinor = (int) $order->payments->where('status', 'verified')->sum('amount_minor');
        $outstandingMinor = max(0, $order->total_minor - $verifiedPaidMinor);

        return Inertia::render('academies/store-order', [
            'order_academy' => [
                'name' => $context->academy()->name,
                'currency' => $context->academy()->currency,
            ],
            'order' => [
                'id' => $order->id,
                'number' => $order->number,
                'source' => $order->source,
                'customer_name' => $order->customer_name,
                'customer_email' => $order->customer_email,
                'customer_phone' => $order->customer_phone,
                'member_name' => $order->member_name,
                'collection_branch' => $order->branch?->name,
                'delivery_method' => $order->delivery_method,
                'delivery_address' => $order->delivery_address,
                'notes' => $order->notes,
                'currency' => $order->currency,
                'subtotal' => Money::fromMinor($order->subtotal_minor),
                'discount' => Money::fromMinor($order->discount_minor),
                'delivery' => Money::fromMinor($order->delivery_minor),
                'total' => Money::fromMinor($order->total_minor),
                'paid' => Money::fromMinor($verifiedPaidMinor),
                'outstanding' => Money::fromMinor($outstandingMinor),
                'payment_status' => $order->payment_status,
                'payment_preference' => $order->payment_preference,
                'fulfillment_status' => $order->fulfillment_status,
                'placed_at' => $order->placed_at?->toIso8601String(),
                'paid_at' => $order->paid_at?->toIso8601String(),
                'fulfilled_at' => $order->fulfilled_at?->toIso8601String(),
                'cancelled_at' => $order->cancelled_at?->toIso8601String(),
                'items' => $order->items->map(fn ($item): array => [
                    'id' => $item->id,
                    'name' => $item->product_name,
                    'variant' => $item->variant_name,
                    'sku' => $item->sku,
                    'unit_price' => Money::fromMinor($item->unit_price_minor),
                    'quantity' => $item->quantity,
                    'line_total' => Money::fromMinor($item->line_total_minor),
                    'allocations' => $item->allocations->map(fn ($allocation): array => [
                        'branch' => $allocation->branch?->name ?? 'Unknown location',
                        'quantity' => $allocation->quantity,
                    ])->values(),
                ])->values(),
                'payments' => $order->payments->map(fn ($payment): array => [
                    'id' => $payment->id,
                    'reference' => $payment->reference,
                    'external_reference' => $payment->external_reference,
                    'method' => $payment->method,
                    'status' => $payment->status,
                    'amount' => Money::fromMinor((int) $payment->amount_minor),
                    'surcharge' => Money::fromMinor((int) ($payment->surcharge_minor ?? 0)),
                    'charged_amount' => Money::fromMinor((int) ($payment->charged_amount_minor ?? $payment->amount_minor)),
                    'provider_fee' => Money::fromMinor((int) ($payment->provider_fee_minor ?? 0)),
                    'received_on' => $payment->received_on?->toDateString(),
                    'verified_at' => $payment->verified_at?->toIso8601String(),
                    'created_at' => $payment->created_at?->toIso8601String(),
                    'failure_reason' => $payment->failure_reason,
                    'notes' => $payment->notes,
                    'recorded_by' => $payment->recordedBy?->name,
                ])->values(),
            ],
        ]);
    }

    public function recordPayment(Request $request, StoreOrder $order, AcademyContext $context, ManageStoreOrder $manage, AuditLogger $audit): RedirectResponse
    {
        $this->authorizeAccess($context);
        $this->tenant($order, $context);
        $data = $request->validate(['amount' => ['required', 'regex:/^\d{1,12}(?:\.\d{1,2})?$/', 'not_in:0,0.00'], 'method' => ['required', Rule::in(['cash', 'transfer', 'card', 'cheque', 'other'])],
            'received_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'], 'external_reference' => ['nullable', 'string', 'max:120'], 'notes' => ['nullable', 'string', 'max:1000']]);
        $payment = $manage->recordPayment($order, Money::toMinor($data['amount']), $data['method'], $data['received_on'], $data['external_reference'] ?? null, $data['notes'] ?? null, $request->user());
        $audit->record('academy.store.order.payment-recorded', $request->user(), $context->academy(), $payment, ['order_id' => $order->id], $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Order payment recorded.']);

        return back();
    }

    public function updateFulfillment(Request $request, StoreOrder $order, AcademyContext $context, ManageStoreOrder $manage, AuditLogger $audit): RedirectResponse
    {
        $this->authorizeAccess($context);
        $this->tenant($order, $context);
        $data = $request->validate(['status' => ['required', Rule::in(['unfulfilled', 'processing', 'ready', 'shipped', 'delivered'])]]);
        $manage->updateFulfillment($order, $data['status'], $request->user());
        $audit->record('academy.store.order.fulfillment-updated', $request->user(), $context->academy(), $order, ['status' => $data['status']], $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Delivery status updated.']);

        return back();
    }

    public function cancelOrder(Request $request, StoreOrder $order, AcademyContext $context, ManageStoreOrder $manage, AuditLogger $audit): RedirectResponse
    {
        $this->authorizeAccess($context);
        $this->tenant($order, $context);
        $manage->cancel($order, $request->user());
        $audit->record('academy.store.order.cancelled', $request->user(), $context->academy(), $order, [], $request);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Order cancelled and reserved stock released.']);

        return back();
    }

    private function authorizeAccess(AcademyContext $context): void
    {
        abort_unless($context->canManageAcademy() || $context->canManageFinance(), 403);
    }

    private function tenant(object $model, AcademyContext $context): void
    {
        abort_unless((int) $model->academy_id === $context->id(), 404);
    }

    /** @return array<string, mixed> */
    private function productData(Request $request, AcademyContext $context, ?int $ignore = null): array
    {
        return $request->validate(['name' => ['required', 'string', 'max:160'], 'sku' => ['nullable', 'string', 'max:80', Rule::unique('products', 'sku')->where('academy_id', $context->id())->ignore($ignore)],
            'description' => ['nullable', 'string', 'max:5000'], 'product_category_id' => ['nullable', 'integer', Rule::exists('product_categories', 'id')->where(fn (Builder $q) => $q->where('academy_id', $context->id()))],
            'price' => ['required', 'regex:/^\d{1,12}(?:\.\d{1,2})?$/'], 'status' => ['required', Rule::in(['draft', 'active', 'archived'])],
            'track_inventory' => ['required', 'boolean'], 'allow_backorder' => ['required', 'boolean'], 'image' => ['nullable', File::image()->max(5 * 1024)], 'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', Rule::exists('product_tags', 'id')->where(fn (Builder $q) => $q->where('academy_id', $context->id()))],
            'variant_name' => ['nullable', 'string', 'max:120'], 'variant_sku' => ['nullable', 'string', 'max:80', Rule::unique('product_variants', 'sku')->where('academy_id', $context->id())]]);
    }

    /** @return array<string, mixed> */
    private function orderData(Request $request, AcademyContext $context): array
    {
        return $request->validate(['customer_name' => ['required', 'string', 'max:160'], 'customer_email' => ['nullable', 'email:rfc', 'max:254'], 'customer_phone' => ['nullable', 'string', 'max:40'], 'member_name' => ['nullable', 'string', 'max:160'],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where(fn (Builder $q) => $q->where('academy_id', $context->id()))], 'delivery_method' => ['required', Rule::in(['pickup', 'delivery'])],
            'delivery_address' => ['nullable', 'required_if:delivery_method,delivery', 'string', 'max:1000'], 'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:50'], 'items.*.variant_id' => ['required', 'integer'], 'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'amount_paid' => ['nullable', 'regex:/^\d{1,12}(?:\.\d{1,2})?$/'], 'payment_method' => ['nullable', 'required_with:amount_paid', Rule::in(['cash', 'transfer', 'card', 'cheque', 'other'])],
            'payment_reference' => ['nullable', 'string', 'max:120'], 'received_on' => ['nullable', 'required_with:amount_paid', 'date_format:Y-m-d', 'before_or_equal:today'], 'mark_delivered' => ['nullable', 'boolean']]);
    }

    private function uniqueSlug(int $academyId, string $name, ?int $ignore = null): string
    {
        return $this->slug(Product::query(), $academyId, $name, $ignore);
    }

    private function categorySlug(int $academyId, string $name): string
    {
        return $this->slug(ProductCategory::query(), $academyId, $name);
    }

    private function tagSlug(int $academyId, string $name): string
    {
        return $this->slug(ProductTag::query(), $academyId, $name);
    }

    private function slug($query, int $academyId, string $name, ?int $ignore = null): string
    {
        $base = Str::slug($name) ?: 'item';
        $slug = $base;
        $i = 2;
        while ((clone $query)->where('academy_id', $academyId)->where('slug', $slug)->when($ignore, fn ($q) => $q->whereKeyNot($ignore))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    /** @return array<string, string> */
    private function adjustmentReasons(): array
    {
        return ['purchase' => 'New purchase / restock', 'opening_stock' => 'Opening stock', 'customer_return' => 'Customer return',
            'stock_count' => 'Stock count correction', 'damaged' => 'Damaged item', 'expired' => 'Expired item', 'lost' => 'Lost or missing item',
            'supplier_return' => 'Returned to supplier', 'other' => 'Other'];
    }

    private function persistProductImage(Product $product, UploadedFile $file, int $academyId): void
    {
        $path = $file->storeAs('academies/'.$academyId.'/products', $product->id.'-'.Str::random(20).'.'.$file->extension(), 'local');
        abort_unless(is_string($path), 500, 'The product image could not be stored.');
        $old = $product->image_path;
        $product->update(['image_path' => $path]);
        if ($old !== null) {
            Storage::disk('local')->delete($old);
        }
    }
}
