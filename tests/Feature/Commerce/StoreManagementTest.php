<?php

namespace Tests\Feature\Commerce;

use App\Enums\AcademyRole;
use App\Models\Academy;
use App\Models\AcademyApiKey;
use App\Models\AcademyMembership;
use App\Models\AcademyWebsite;
use App\Models\Branch;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\StoreOrder;
use App\Models\StoreOrderPayment;
use App\Models\User;
use App\Notifications\StoreOrderPaymentReceiptNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StoreManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_build_catalogue_and_adjust_stock_in_minor_units(): void
    {
        Storage::fake('local');
        [$owner, $academy] = $this->owner();
        $branch = Branch::factory()->for($academy)->create();
        $this->asAcademy($owner, $academy)->post(route('academy.store.categories.store'), ['name' => 'Uniforms'])
            ->assertRedirect()->assertInertiaFlash('toast.message', 'Product category created.');
        $category = $academy->productCategories()->sole();

        $this->asAcademy($owner, $academy)->post(route('academy.store.products.store'), [
            'name' => 'Training Jersey', 'sku' => 'JER-BASE', 'product_category_id' => $category->id,
            'description' => 'Official training jersey', 'price' => '12500.50', 'status' => 'active',
            'track_inventory' => true, 'allow_backorder' => false, 'variant_name' => 'Medium', 'variant_sku' => 'JER-M',
            'image' => UploadedFile::fake()->image('jersey.jpg', 1200, 900),
        ])->assertRedirect()->assertInertiaFlash('toast.message', 'Product created. Add stock before publishing it for sale.');

        $product = Product::query()->where('academy_id', $academy->id)->sole();
        $variant = $product->variants()->sole();
        $this->assertSame(1250050, $product->base_price_minor);
        $this->assertSame('Medium', $variant->name);
        Storage::disk('local')->assertExists($product->image_path);

        $this->asAcademy($owner, $academy)->post(route('academy.store.stock.adjust', $variant), [
            'branch_id' => $branch->id, 'direction' => 'add', 'quantity' => 12, 'reason_code' => 'opening_stock',
        ])->assertRedirect()->assertInertiaFlash('toast.message', 'Stock adjustment recorded.');
        $stock = InventoryStock::query()->where('product_variant_id', $variant->id)->sole();
        $this->assertSame(12, $stock->on_hand);
        $this->assertSame(0, $stock->reserved);
        $this->assertDatabaseHas('stock_movements', ['academy_id' => $academy->id, 'type' => 'adjustment_in', 'on_hand_delta' => 12]);
        $this->assertDatabaseHas('audit_logs', ['academy_id' => $academy->id, 'action' => 'academy.store.stock.adjusted']);

        $this->asAcademy($owner, $academy)->post(route('academy.store.stock.adjust', $variant), [
            'branch_id' => $branch->id, 'direction' => 'remove', 'quantity' => 2, 'reason_code' => 'other', 'reason_details' => 'Two samples used for display',
        ])->assertRedirect();
        $this->assertSame(10, $stock->fresh()->on_hand);
        $this->assertDatabaseHas('stock_movements', ['academy_id' => $academy->id, 'type' => 'adjustment_out', 'on_hand_delta' => -2,
            'notes' => 'Other: Two samples used for display']);

        $oldImage = $product->image_path;
        $this->asAcademy($owner, $academy)->post(route('academy.store.products.image.update', $product), [
            'image' => UploadedFile::fake()->image('new-jersey.jpg', 1200, 900),
        ])->assertRedirect()->assertInertiaFlash('toast.message', 'Product image updated.');
        Storage::disk('local')->assertExists($product->fresh()->image_path);
        Storage::disk('local')->assertMissing($oldImage);
        $this->asAcademy($owner, $academy)->get(route('academy.store.products.image', $product))->assertOk()->assertHeader('cache-control', 'no-store, private');

        $emptyBranch = Branch::factory()->for($academy)->create(['name' => 'Empty Branch']);
        InventoryStock::query()->create([
            'academy_id' => $academy->id,
            'product_variant_id' => $variant->id,
            'branch_id' => $emptyBranch->id,
            'branch_scope_id' => $emptyBranch->id,
            'on_hand' => 0,
            'reserved' => 0,
        ]);

        $this->asAcademy($owner, $academy)->get(route('academy.store.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('products.0.inventory_total', 10)
                ->has('products.0.variants.0.stocks', 1)
                ->where('products.0.variants.0.stocks.0.branch_id', $branch->id));
    }

    public function test_stock_can_be_transferred_between_branches_without_moving_reserved_units(): void
    {
        [$owner, $academy] = $this->owner();
        $sourceBranch = Branch::factory()->for($academy)->create(['name' => 'Source Branch']);
        $destinationBranch = Branch::factory()->for($academy)->create(['name' => 'Destination Branch']);
        $product = Product::query()->create(['academy_id' => $academy->id, 'name' => 'Football', 'slug' => 'football', 'currency' => 'NGN',
            'base_price_minor' => 100000, 'status' => 'active', 'track_inventory' => true, 'allow_backorder' => false]);
        $variant = $product->variants()->create(['academy_id' => $academy->id, 'name' => 'Size 5', 'is_active' => true]);
        InventoryStock::query()->create(['academy_id' => $academy->id, 'product_variant_id' => $variant->id, 'branch_id' => $sourceBranch->id,
            'branch_scope_id' => $sourceBranch->id, 'on_hand' => 10, 'reserved' => 2]);

        $this->asAcademy($owner, $academy)->post(route('academy.store.stock.transfer', $variant), [
            'from_branch_id' => $sourceBranch->id, 'to_branch_id' => $destinationBranch->id, 'quantity' => 5, 'reason_code' => 'branch_restock',
        ])->assertRedirect()->assertInertiaFlash('toast.type', 'success');

        $source = InventoryStock::query()->where('product_variant_id', $variant->id)->where('branch_id', $sourceBranch->id)->sole();
        $destination = InventoryStock::query()->where('product_variant_id', $variant->id)->where('branch_id', $destinationBranch->id)->sole();
        $this->assertSame(5, $source->on_hand);
        $this->assertSame(2, $source->reserved);
        $this->assertSame(5, $destination->on_hand);
        $this->assertSame(10, $source->on_hand + $destination->on_hand);
        $movements = StockMovement::query()->whereNotNull('transfer_reference')->get();
        $this->assertCount(2, $movements);
        $this->assertSame(1, $movements->pluck('transfer_reference')->unique()->count());
        $this->assertEqualsCanonicalizing(['transfer_in', 'transfer_out'], $movements->pluck('type')->all());

        $this->asAcademy($owner, $academy)->post(route('academy.store.stock.transfer', $variant), [
            'from_branch_id' => $sourceBranch->id, 'to_branch_id' => $destinationBranch->id, 'quantity' => 4, 'reason_code' => 'stock_rebalancing',
        ])->assertSessionHasErrors('quantity');
        $this->assertSame(5, $source->fresh()->on_hand);
        $this->assertDatabaseHas('audit_logs', ['academy_id' => $academy->id, 'action' => 'academy.store.stock.transferred']);
    }

    public function test_public_store_combines_branch_inventory_and_preserves_each_reservation_allocation(): void
    {
        [, $academy] = $this->owner();
        $first = Branch::factory()->for($academy)->create();
        $second = Branch::factory()->for($academy)->create();
        $website = AcademyWebsite::query()->create(['academy_id' => $academy->id, 'subdomain' => 'combined-'.$academy->id, 'theme_key' => 'launch',
            'is_published' => true, 'sections' => ['store' => ['enabled' => true, 'title' => 'Store', 'intro' => 'Official items']]]);
        $product = Product::query()->create(['academy_id' => $academy->id, 'name' => 'Branch Jersey', 'slug' => 'branch-jersey', 'currency' => 'NGN',
            'base_price_minor' => 100000, 'status' => 'active', 'track_inventory' => true, 'allow_backorder' => false]);
        $variant = $product->variants()->create(['academy_id' => $academy->id, 'name' => 'Default', 'is_active' => true]);
        InventoryStock::query()->create(['academy_id' => $academy->id, 'product_variant_id' => $variant->id, 'branch_id' => $first->id,
            'branch_scope_id' => $first->id, 'on_hand' => 2]);
        InventoryStock::query()->create(['academy_id' => $academy->id, 'product_variant_id' => $variant->id, 'branch_id' => $second->id,
            'branch_scope_id' => $second->id, 'on_hand' => 3]);

        $this->post(route('public.academy.store-order', $website->subdomain), [
            'customer_name' => 'Combined Buyer', 'customer_email' => 'combined@example.test', 'customer_phone' => '0801',
            'branch_id' => $second->id, 'delivery_method' => 'pickup', 'items' => [['variant_id' => $variant->id, 'quantity' => 4]],
        ])->assertRedirect();

        $order = StoreOrder::query()->sole();
        $allocations = $order->items()->sole()->allocations()->orderBy('branch_id')->get();
        $this->assertSame($second->id, $order->branch_id);
        $this->assertSame(4, $allocations->sum('quantity'));
        $this->assertCount(2, $allocations);
        $this->assertSame($first->id, $allocations->first()->branch_id);
        $this->assertSame(4, InventoryStock::query()->where('product_variant_id', $variant->id)->sum('reserved'));
        $this->assertSame(5, InventoryStock::query()->where('product_variant_id', $variant->id)->sum('on_hand'));
    }

    public function test_public_checkout_uses_server_price_reserves_stock_and_cancellation_releases_it(): void
    {
        [$owner, $academy] = $this->owner();
        [$website, $variant] = $this->sellable($academy, 5, 250000);

        $response = $this->post(route('public.academy.store-order', $website->subdomain), [
            'customer_name' => 'Public Buyer', 'customer_email' => 'buyer@example.test', 'customer_phone' => '08010000000',
            'delivery_method' => 'pickup', 'items' => [['variant_id' => $variant->id, 'quantity' => 2, 'price' => '1.00']],
        ])->assertRedirect();
        $order = StoreOrder::query()->sole();
        $this->assertSame(500000, $order->total_minor);
        $this->assertStringContainsString('/store/orders/', $response->headers->get('Location'));
        $this->assertSame(2, InventoryStock::query()->where('product_variant_id', $variant->id)->sole()->reserved);

        $this->asAcademy($owner, $academy)->patch(route('academy.store.orders.cancel', $order))
            ->assertRedirect()->assertInertiaFlash('toast.message', 'Order cancelled and reserved stock released.');
        $this->assertSame(0, InventoryStock::query()->where('product_variant_id', $variant->id)->sole()->reserved);
        $this->assertSame('cancelled', $order->fresh()->fulfillment_status);
        $this->assertDatabaseHas('stock_movements', ['type' => 'release', 'reserved_delta' => -2]);
    }

    public function test_public_store_order_can_capture_member_name_and_settle_with_paystack(): void
    {
        Notification::fake();
        config()->set('services.paystack.secret_key', 'sk_test_store');
        [, $academy] = $this->owner();
        $academy->update([
            'bank_name' => 'Example Bank',
            'bank_account_name' => 'Example Academy',
            'bank_account_number' => '0123456789',
        ]);
        [$website, $variant] = $this->sellable($academy, 5, 250000);

        $paystackStatus = 'abandoned';
        Http::fake(function (ClientRequest $request) use (&$paystackStatus) {
            if (str_ends_with($request->url(), '/transaction/initialize')) {
                return Http::response(['status' => true, 'data' => [
                    'access_code' => 'access-store-123',
                    'reference' => $request->data()['reference'],
                ]]);
            }

            $reference = basename(parse_url($request->url(), PHP_URL_PATH));
            $payment = StoreOrderPayment::query()->where('reference', $reference)->sole();

            return Http::response(['status' => true, 'data' => [
                'status' => $paystackStatus, 'reference' => $reference,
                'amount' => $payment->charged_amount_minor, 'currency' => $payment->currency,
                'fees' => 5000,
            ]]);
        });

        $response = $this->post(route('public.academy.store-order', $website->subdomain), [
            'customer_name' => 'Store Parent', 'customer_email' => 'store-parent@example.test',
            'customer_phone' => '08010000000', 'member_name' => 'Existing Player',
            'notes' => 'Please prepare this for Saturday collection.',
            'payment_option' => 'paystack', 'delivery_method' => 'pickup',
            'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
        ])->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertStringContainsString('payment=paystack', $location);
        $token = basename((string) parse_url($location, PHP_URL_PATH));
        $order = StoreOrder::query()->sole();
        $this->assertSame('Existing Player', $order->member_name);
        $this->assertSame('Please prepare this for Saturday collection.', $order->notes);

        $this->get(route('public.academy.store-order.show', [$website->subdomain, $token]).'?payment=paystack')
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('public/store-order')
            ->where('order.member_name', 'Existing Player')
            ->where('payment_preference', 'paystack')
            ->where('bank_details.account_number', '0123456789'));

        $initialized = $this->postJson(route('public.academy.store-payment-initialize', [$website->subdomain, $token]), [
            'email' => 'corrected-store-parent@example.test',
        ])
            ->assertOk()->assertJsonPath('access_code', 'access-store-123');
        $payment = StoreOrderPayment::query()->sole();
        $this->assertSame('corrected-store-parent@example.test', $order->fresh()->customer_email);
        $this->assertSame('pending', $payment->status);
        $this->assertGreaterThan($payment->amount_minor, $payment->charged_amount_minor);

        // Paystack calls a transaction "abandoned" until the customer finishes paying; it must stay open.
        $this->postJson($initialized->json('verify_url'));
        $this->assertSame('pending', $payment->fresh()->status);

        $paystackStatus = 'success';
        $this->postJson($initialized->json('verify_url'))->assertOk()->assertJsonPath('status', 'verified');
        $this->assertSame('verified', $payment->fresh()->status);
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertDatabaseHas('audit_logs', ['academy_id' => $academy->id, 'action' => 'academy.store.payment.online-verified']);
        Notification::assertSentOnDemand(StoreOrderPaymentReceiptNotification::class);

        $this->postJson(route('public.academy.store-payment-initialize', [$website->subdomain, 'wrong-token']))->assertNotFound();
    }

    public function test_enabled_store_is_exposed_as_the_built_in_public_website_module(): void
    {
        [$owner, $academy] = $this->owner();
        [$website] = $this->sellable($academy, 3, 250000);

        $this->asAcademy($owner, $academy)->get(route('academy.website.edit'))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('academies/website')
            ->where('store_module.enabled', true)
            ->where('store_module.active_product_count', 1)
            ->where('store_module.preview_url', route('public.academy.store', $website->subdomain)));

        $this->get(route('public.academy.store', $website->subdomain))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('public/store')
            ->where('site.sections.store.enabled', true)
            ->where('site.sections.store.title', 'Shop')
            ->where('products.0.name', 'Academy Jersey'));

        $website->update(['sections' => ['store' => ['enabled' => false, 'title' => 'Shop', 'intro' => 'Official items']]]);
        $this->get(route('public.academy.store', $website->subdomain))->assertNotFound();
    }

    public function test_offline_sale_can_be_paid_and_delivered_without_double_consuming_stock(): void
    {
        [$owner, $academy] = $this->owner();
        [, $variant] = $this->sellable($academy, 10, 100000);
        $payload = ['customer_name' => 'Walk-in Customer', 'delivery_method' => 'pickup', 'items' => [['variant_id' => $variant->id, 'quantity' => 3]],
            'amount_paid' => '3000.00', 'payment_method' => 'cash', 'received_on' => now()->toDateString(), 'mark_delivered' => true];
        $this->asAcademy($owner, $academy)->post(route('academy.store.orders.store'), $payload)
            ->assertRedirect();
        $order = StoreOrder::query()->sole();
        $stock = InventoryStock::query()->where('product_variant_id', $variant->id)->sole();
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('delivered', $order->fulfillment_status);
        $this->assertSame(7, $stock->on_hand);
        $this->assertSame(0, $stock->reserved);
        $this->assertSame(1, StockMovement::query()->where('type', 'sale')->count());

        $this->asAcademy($owner, $academy)->patch(route('academy.store.orders.fulfillment.update', $order), ['status' => 'delivered'])->assertRedirect();
        $this->assertSame(7, $stock->fresh()->on_hand);
        $this->assertSame(1, StockMovement::query()->where('type', 'sale')->count());
    }

    public function test_order_details_show_items_allocations_payment_history_and_outstanding_balance(): void
    {
        [$owner, $academy] = $this->owner();
        [, $variant] = $this->sellable($academy, 10, 100000);
        $this->asAcademy($owner, $academy)->post(route('academy.store.orders.store'), [
            'customer_name' => 'Parent Customer',
            'customer_email' => 'parent@example.test',
            'member_name' => 'Student Name',
            'delivery_method' => 'pickup',
            'items' => [['variant_id' => $variant->id, 'quantity' => 3]],
            'amount_paid' => '1000.00',
            'payment_method' => 'transfer',
            'payment_reference' => 'BANK-001',
            'received_on' => now()->toDateString(),
        ])->assertRedirect();
        $order = StoreOrder::query()->sole();

        $this->asAcademy($owner, $academy)->get(route('academy.store.orders.show', $order))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('academies/store-order')
                ->where('order_academy.name', $academy->name)
                ->where('academy.id', $academy->id)
                ->where('academy.can_manage', true)
                ->where('order.number', $order->number)
                ->where('order.total', '3000.00')
                ->where('order.paid', '1000.00')
                ->where('order.outstanding', '2000.00')
                ->where('order.payment_status', 'partially_paid')
                ->where('order.items.0.name', 'Academy Jersey')
                ->where('order.items.0.quantity', 3)
                ->where('order.items.0.allocations.0.quantity', 3)
                ->where('order.payments.0.status', 'verified')
                ->where('order.payments.0.external_reference', 'BANK-001')
                ->where('order.payments.0.provider_fee', '0.00'));

        [$otherOwner, $otherAcademy] = $this->owner();
        $this->asAcademy($otherOwner, $otherAcademy)
            ->get(route('academy.store.orders.show', $order))
            ->assertNotFound();
    }

    public function test_overselling_and_cross_tenant_mutation_are_blocked(): void
    {
        [$owner, $academy] = $this->owner();
        [$website, $variant] = $this->sellable($academy, 1, 100000);
        $this->post(route('public.academy.store-order', $website->subdomain), ['customer_name' => 'Buyer', 'customer_email' => 'buyer@example.test',
            'customer_phone' => '0801', 'delivery_method' => 'pickup', 'items' => [['variant_id' => $variant->id, 'quantity' => 2]]])->assertSessionHasErrors('items');
        $this->assertDatabaseCount('store_orders', 0);

        [$otherOwner, $otherAcademy] = $this->owner();
        $this->asAcademy($otherOwner, $otherAcademy)->post(route('academy.store.stock.adjust', $variant), ['quantity' => 2, 'reason' => 'Attack'])->assertNotFound();
        $this->assertSame(1, InventoryStock::query()->where('product_variant_id', $variant->id)->sole()->on_hand);
        $this->asAcademy($owner, $academy)->get(route('academy.store.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('academies/store')->has('products', 1));
    }

    public function test_api_catalog_and_checkout_are_scoped_to_authenticated_academy_key(): void
    {
        [, $academy] = $this->owner();
        [, $variant] = $this->sellable($academy, 4, 175000);
        $secret = 'aca_'.str_repeat('a', 48);
        AcademyApiKey::query()->create(['academy_id' => $academy->id, 'name' => 'Storefront', 'prefix' => substr($secret, 0, 12), 'secret_hash' => hash('sha256', $secret)]);
        $this->withToken($secret)->getJson(route('api.v1.store.catalog'))->assertOk()->assertJsonPath('data.0.name', 'Academy Jersey');
        $this->withToken($secret)->postJson(route('api.v1.store.orders.store'), ['customer_name' => 'API Buyer', 'delivery_method' => 'pickup',
            'items' => [['variant_id' => $variant->id, 'quantity' => 1]]])->assertCreated()->assertJsonPath('data.total', '1750.00');
        $this->assertDatabaseHas('store_orders', ['academy_id' => $academy->id, 'source' => 'api', 'total_minor' => 175000]);
    }

    /** @return array{User, Academy} */
    private function owner(): array
    {
        $owner = User::factory()->create();
        $academy = Academy::factory()->for($owner, 'creator')->create();
        AcademyMembership::factory()->for($owner)->for($academy)->asRole(AcademyRole::OwnerAdmin)->create();

        return [$owner, $academy];
    }

    /** @return array{AcademyWebsite, ProductVariant} */
    private function sellable(Academy $academy, int $stock, int $price): array
    {
        $website = AcademyWebsite::query()->create(['academy_id' => $academy->id, 'subdomain' => 'academy-'.$academy->id, 'theme_key' => 'classic', 'is_published' => true,
            'sections' => ['store' => ['enabled' => true, 'title' => 'Shop', 'intro' => 'Official items']]]);
        $product = Product::query()->create(['academy_id' => $academy->id, 'name' => 'Academy Jersey', 'slug' => 'academy-jersey', 'currency' => $academy->currency,
            'base_price_minor' => $price, 'status' => 'active', 'track_inventory' => true, 'allow_backorder' => false]);
        $variant = $product->variants()->create(['academy_id' => $academy->id, 'name' => 'Default', 'is_active' => true]);
        $branch = Branch::factory()->for($academy)->create();
        InventoryStock::query()->create(['academy_id' => $academy->id, 'product_variant_id' => $variant->id, 'branch_id' => $branch->id,
            'branch_scope_id' => $branch->id, 'on_hand' => $stock]);

        return [$website, $variant];
    }

    private function asAcademy(User $user, Academy $academy): static
    {
        return $this->actingAs($user)->withSession(['active_academy_id' => $academy->id]);
    }
}
