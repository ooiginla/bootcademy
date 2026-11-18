<?php

namespace Tests\Feature\Academies;

use App\Enums\AcademyRole;
use App\Models\Academy;
use App\Models\AcademyMembership;
use App\Models\AcademyWebsite;
use App\Models\Branch;
use App\Models\Coupon;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StoreOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CouponManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_users_can_create_and_edit_tenant_coupons(): void
    {
        [$owner, $academy] = $this->owner();

        $this->asAcademy($owner, $academy)->post(route('academy.coupons.store'), [
            'code' => ' welcome-10 ',
            'name' => 'Welcome offer',
            'discount_type' => 'percentage',
            'percentage' => '10.50',
            'applies_to' => ['application_fee', 'programme_fee'],
            'minimum_amount' => '5000.00',
            'maximum_redemptions' => '100',
            'per_customer_limit' => '1',
            'is_active' => true,
        ])->assertRedirect()->assertInertiaFlash('toast.message', 'Coupon created.');

        $coupon = Coupon::query()->sole();
        $this->assertSame($academy->id, $coupon->academy_id);
        $this->assertSame('WELCOME-10', $coupon->code);
        $this->assertSame(1050, $coupon->percentage_basis_points);
        $this->assertSame(500000, $coupon->minimum_amount_minor);
        $this->assertDatabaseHas('audit_logs', [
            'academy_id' => $academy->id,
            'action' => 'academy.coupon.created',
        ]);

        $this->asAcademy($owner, $academy)->patch(route('academy.coupons.update', $coupon), [
            'code' => 'WELCOME-FIXED',
            'name' => 'Fixed welcome offer',
            'discount_type' => 'fixed',
            'fixed_amount' => '2500.00',
            'applies_to' => ['store'],
            'is_active' => false,
        ])->assertRedirect()->assertInertiaFlash('toast.message', 'Coupon updated.');

        $coupon->refresh();
        $this->assertSame(250000, $coupon->fixed_amount_minor);
        $this->assertNull($coupon->percentage_basis_points);
        $this->assertFalse($coupon->is_active);
        $this->asAcademy($owner, $academy)->get(route('academy.coupons.index'))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('academies/coupons')
            ->has('coupons', 1)
            ->where('coupons.0.code', 'WELCOME-FIXED')
            ->where('coupons.0.is_active', false));
    }

    public function test_coupon_management_is_authorized_and_cross_tenant_updates_are_hidden(): void
    {
        [$owner, $academy] = $this->owner();
        [, $otherAcademy] = $this->owner();
        $foreignCoupon = $this->coupon($otherAcademy, 'FOREIGN');
        $coach = User::factory()->create();
        AcademyMembership::factory()->for($academy)->for($coach)->asRole(AcademyRole::Coach)->create();

        $this->asAcademy($coach, $academy)->get(route('academy.coupons.index'))->assertForbidden();
        $this->asAcademy($owner, $academy)->patch(route('academy.coupons.update', $foreignCoupon), [
            'code' => 'CHANGED', 'name' => 'Changed', 'discount_type' => 'percentage',
            'percentage' => '25', 'applies_to' => ['store'], 'is_active' => true,
        ])->assertNotFound();

        $this->assertSame('FOREIGN', $foreignCoupon->fresh()->code);
    }

    public function test_store_coupon_is_applied_before_payment_and_limits_are_transactional(): void
    {
        [$owner, $academy] = $this->owner();
        [$website, $variant, $stock] = $this->store($academy);
        $this->coupon($academy, 'SAVE20', [
            'percentage_basis_points' => 2000,
            'per_customer_limit' => 1,
        ]);
        $payload = [
            'customer_name' => 'Coupon Buyer',
            'customer_email' => 'buyer@example.test',
            'customer_phone' => '08010000000',
            'delivery_method' => 'pickup',
            'payment_option' => 'pay_later',
            'items' => [['variant_id' => $variant->id, 'quantity' => 2]],
        ];

        $this->post(route('public.academy.store-order', $website->subdomain), [
            ...$payload, 'coupon_code' => 'NO-SUCH-CODE',
        ])->assertSessionHasErrors('coupon_code');
        $this->assertDatabaseCount('store_orders', 0);
        $this->assertSame(0, $stock->fresh()->reserved);

        $response = $this->post(route('public.academy.store-order', $website->subdomain), [
            ...$payload, 'coupon_code' => 'save20',
        ])->assertRedirect();
        $order = StoreOrder::query()->sole();
        $this->assertSame(500000, $order->subtotal_minor);
        $this->assertSame(100000, $order->discount_minor);
        $this->assertSame(400000, $order->total_minor);
        $this->assertDatabaseHas('coupon_redemptions', [
            'academy_id' => $academy->id,
            'store_order_id' => $order->id,
            'discount_minor' => 100000,
            'status' => 'applied',
        ]);

        $token = basename((string) parse_url((string) $response->headers->get('Location'), PHP_URL_PATH));
        $this->get(route('public.academy.store-order.show', [$website->subdomain, $token]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('order.subtotal', '5000.00')
            ->where('order.discount', '1000.00')
            ->where('order.total', '4000.00'));

        $this->post(route('public.academy.store-order', $website->subdomain), [
            ...$payload, 'coupon_code' => 'SAVE20',
        ])->assertSessionHasErrors('coupon_code');
        $this->assertDatabaseCount('store_orders', 1);
        $this->assertSame(2, $stock->fresh()->reserved);

        $this->asAcademy($owner, $academy)->patch(route('academy.store.orders.cancel', $order))->assertRedirect();
        $this->assertDatabaseHas('coupon_redemptions', [
            'store_order_id' => $order->id,
            'status' => 'cancelled',
        ]);
        $this->post(route('public.academy.store-order', $website->subdomain), [
            ...$payload, 'coupon_code' => 'SAVE20',
        ])->assertRedirect();
        $this->assertDatabaseCount('store_orders', 2);
    }

    /** @return array{User, Academy} */
    private function owner(): array
    {
        $owner = User::factory()->create();
        $academy = Academy::factory()->for($owner, 'creator')->create();
        AcademyMembership::factory()->for($owner)->for($academy)->asRole(AcademyRole::OwnerAdmin)->create();

        return [$owner, $academy];
    }

    /** @param array<string, mixed> $attributes */
    private function coupon(Academy $academy, string $code, array $attributes = []): Coupon
    {
        return Coupon::query()->create([
            'academy_id' => $academy->id,
            'code' => $code,
            'name' => $code.' coupon',
            'discount_type' => 'percentage',
            'percentage_basis_points' => 1000,
            'currency' => $academy->currency,
            'applies_to' => ['store'],
            'per_customer_limit' => 1,
            'is_active' => true,
            'created_by_user_id' => $academy->created_by_user_id,
            ...$attributes,
        ]);
    }

    /** @return array{AcademyWebsite, ProductVariant, InventoryStock} */
    private function store(Academy $academy): array
    {
        $website = AcademyWebsite::query()->create([
            'academy_id' => $academy->id,
            'subdomain' => 'coupon-store-'.$academy->id,
            'theme_key' => 'classic',
            'is_published' => true,
            'sections' => ['store' => ['enabled' => true, 'title' => 'Shop', 'intro' => 'Official items']],
        ]);
        $product = Product::query()->create([
            'academy_id' => $academy->id,
            'name' => 'Training shirt',
            'slug' => 'training-shirt',
            'currency' => $academy->currency,
            'base_price_minor' => 250000,
            'status' => 'active',
            'track_inventory' => true,
            'allow_backorder' => false,
        ]);
        $variant = $product->variants()->create([
            'academy_id' => $academy->id,
            'name' => 'Default',
            'is_active' => true,
        ]);
        $branch = Branch::factory()->for($academy)->create();
        $stock = InventoryStock::query()->create([
            'academy_id' => $academy->id,
            'product_variant_id' => $variant->id,
            'branch_id' => $branch->id,
            'branch_scope_id' => $branch->id,
            'on_hand' => 10,
        ]);

        return [$website, $variant, $stock];
    }

    private function asAcademy(User $user, Academy $academy): static
    {
        return $this->actingAs($user)->withSession(['active_academy_id' => $academy->id]);
    }
}
