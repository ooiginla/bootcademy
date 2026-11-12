<?php

namespace App\Http\Controllers;

use App\Academies\AcademyWebsiteDefaults;
use App\Commerce\PlaceStoreOrder;
use App\Commerce\SettleStoreOrderPayment;
use App\Models\AcademyWebsite;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\StoreOrder;
use App\Models\StoreOrderPayment;
use App\Payments\Contracts\PaymentProvider;
use App\Payments\PaystackFeeCalculator;
use App\Platform\AuditLogger;
use App\Rules\PassesRecaptcha;
use App\Support\AccessibleColor;
use App\Support\Money;
use Illuminate\Database\Query\Builder;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicStoreController extends Controller
{
    public function index(Request $request, string $subdomain): Response
    {
        [$website, $common] = $this->resolve($request, $subdomain);

        return Inertia::render('public/store', $common + $this->catalog($website, $common['base_url']));
    }

    public function store(Request $request, string $subdomain, PlaceStoreOrder $place, AuditLogger $audit): RedirectResponse
    {
        [$website, $common] = $this->resolve($request, $subdomain);
        $academy = $website->academy;
        $data = $request->validate(['recaptcha_token' => [new PassesRecaptcha('store_order', $request->ip())], 'customer_name' => ['required', 'string', 'max:160'], 'customer_email' => ['required', 'email:rfc', 'max:254'],
            'customer_phone' => ['required', 'string', 'max:40'], 'member_name' => ['nullable', 'string', 'max:160'],
            'payment_option' => ['nullable', Rule::in(['paystack', 'bank_transfer', 'pay_later'])],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->withoutTrashed()->where(fn (Builder $q) => $q->where('academy_id', $academy->id)->where('is_active', true))],
            'delivery_method' => ['required', Rule::in(['pickup', 'delivery'])], 'delivery_address' => ['nullable', 'required_if:delivery_method,delivery', 'string', 'max:1000'], 'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:50'], 'items.*.variant_id' => ['required', 'integer'], 'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100']]);
        unset($data['recaptcha_token']);
        $data['payment_option'] ??= 'pay_later';
        if ($data['payment_option'] === 'paystack' && blank(config('services.paystack.secret_key'))) {
            throw ValidationException::withMessages(['payment_option' => 'Online payment is temporarily unavailable. Choose another payment option.']);
        }
        if ($data['payment_option'] === 'bank_transfer' && blank($academy->bank_account_number)) {
            throw ValidationException::withMessages(['payment_option' => 'Bank transfer is not configured for this academy.']);
        }
        $result = $place->handle($academy, $data);
        $audit->record('academy.store.order.created', null, $academy, $result['order'], ['source' => 'storefront'], $request);

        return redirect($common['base_url'].'/store/orders/'.$result['token'].'?payment='.urlencode($data['payment_option']))
            ->with('toast', ['type' => 'success', 'message' => 'Your order has been received.']);
    }

    public function show(Request $request, string $subdomain, string $token): Response
    {
        [$website, $common] = $this->resolve($request, $subdomain);
        $order = StoreOrder::query()->where('academy_id', $website->academy_id)->where('public_token_hash', hash('sha256', $token))->with(['items', 'payments', 'branch'])->firstOrFail();

        $academy = $website->academy;
        $paidMinor = (int) $order->payments->where('status', 'verified')->sum('amount_minor');
        $outstandingMinor = max(0, $order->total_minor - $paidMinor);
        $fee = app(PaystackFeeCalculator::class)->surcharge($academy, $outstandingMinor);

        return Inertia::render('public/store-order', $common + ['order_token' => $token, 'order' => ['number' => $order->number, 'customer_name' => $order->customer_name,
            'customer_email' => $order->customer_email, 'member_name' => $order->member_name,
            'currency' => $order->currency, 'total' => Money::fromMinor($order->total_minor), 'paid' => Money::fromMinor((int) $order->payments->where('status', 'verified')->sum('amount_minor')),
            'payment_status' => $order->payment_status, 'fulfillment_status' => $order->fulfillment_status, 'delivery_method' => $order->delivery_method,
            'branch' => $order->branch?->name, 'items' => $order->items->map(fn ($item) => ['name' => $item->product_name, 'variant' => $item->variant_name, 'quantity' => $item->quantity, 'unit_price' => Money::fromMinor($item->unit_price_minor)]),
            'outstanding' => Money::fromMinor($outstandingMinor), 'processing_fee' => Money::fromMinor($fee),
            'online_total' => Money::fromMinor($outstandingMinor + $fee)],
            'payment_preference' => $order->payment_preference,
            'paystack_ready' => filled(config('services.paystack.secret_key')),
            'bank_details' => ['bank_name' => $academy->bank_name, 'account_name' => $academy->bank_account_name, 'account_number' => $academy->bank_account_number],
        ]);
    }

    public function initializePayment(
        Request $request,
        string $subdomain,
        string $token,
        PaymentProvider $provider,
        PaystackFeeCalculator $fees,
    ): JsonResponse {
        [$website, , $order] = $this->authorizedOrder($request, $subdomain, $token);
        $paymentEmail = strtolower(trim((string) $request->input('email', $order->customer_email)));
        $request->merge(['email' => $paymentEmail]);
        $request->validate(['email' => ['required', 'email:rfc', 'max:254']]);
        if ($paymentEmail !== $order->customer_email) {
            $order->update(['customer_email' => $paymentEmail]);
        }
        $alreadyPaid = (int) $order->payments()->where('status', 'verified')->sum('amount_minor');
        $base = max(0, $order->total_minor - $alreadyPaid);
        abort_if($base === 0, 422, 'This order is already paid.');
        $surcharge = $fees->surcharge($website->academy, $base);
        $charged = $base + $surcharge;
        $existing = $order->payments()->where('method', 'paystack')->where('status', 'pending')
            ->where('amount_minor', $base)->where('charged_amount_minor', $charged)
            ->where('created_at', '>=', now()->subMinutes(30))->whereNotNull('access_code')->latest()->first();
        if ($existing !== null) {
            return $this->paymentResponse($request, $subdomain, $token, $existing);
        }

        $reference = 'STP-'.$order->academy_id.'-'.Str::upper(Str::random(20));
        $payment = $order->payments()->create([
            'academy_id' => $order->academy_id, 'reference' => $reference, 'method' => 'paystack',
            'status' => 'pending', 'currency' => $order->currency, 'amount_minor' => $base,
            'surcharge_minor' => $surcharge, 'charged_amount_minor' => $charged,
            'received_on' => now()->toDateString(), 'notes' => 'Initialized from the public academy store.',
        ]);
        try {
            $initialized = $provider->initialize($paymentEmail, $charged, $order->currency, $reference, [
                'academy_id' => $order->academy_id, 'store_order_id' => $order->id,
                'store_order_number' => $order->number, 'store_order_payment_id' => $payment->id,
            ]);
            $payment->update(['access_code' => $initialized['access_code'], 'initialized_at' => now()]);
        } catch (RuntimeException $exception) {
            $payment->update(['status' => 'failed', 'failure_reason' => $exception->getMessage()]);

            return response()->json(['message' => $exception->getMessage()], 502);
        }

        return $this->paymentResponse($request, $subdomain, $token, $payment->fresh());
    }

    public function verifyPayment(
        Request $request,
        string $subdomain,
        string $token,
        StoreOrderPayment $payment,
        PaymentProvider $provider,
        SettleStoreOrderPayment $settle,
    ): JsonResponse {
        [, , $order] = $this->authorizedOrder($request, $subdomain, $token);
        abort_unless($payment->academy_id === $order->academy_id && $payment->store_order_id === $order->id, 404);
        if (! in_array($payment->status, ['verified', 'review_required'], true)) {
            try {
                $payment = $settle->handle($payment, $provider->verify($payment->reference), $request);
            } catch (RuntimeException $exception) {
                return response()->json(['status' => $payment->fresh()->status, 'message' => $exception->getMessage()], 202);
            }
        }

        return response()->json([
            'status' => $payment->status,
            'message' => $payment->status === 'verified' ? 'Payment verified. Your receipt has been emailed.' : $payment->failure_reason,
        ], $payment->status === 'verified' ? 200 : 202);
    }

    public function image(Request $request, string $subdomain, int $product): StreamedResponse
    {
        [$website] = $this->resolve($request, $subdomain);
        $record = $website->academy->products()->where('status', 'active')->findOrFail($product);
        abort_if(! $record->image_path, 404);
        /** @var FilesystemAdapter $disk */ $disk = Storage::disk('local');
        abort_unless($disk->exists($record->image_path), 404);

        return $disk->response($record->image_path, null, ['Cache-Control' => 'public, max-age=3600', 'X-Content-Type-Options' => 'nosniff']);
    }

    /** @return array<string, mixed> */
    private function catalog(AcademyWebsite $website, string $baseUrl): array
    {
        $academy = $website->academy;

        return ['products' => $academy->products()->where('status', 'active')->with(['category', 'tags', 'variants' => fn ($q) => $q->where('is_active', true)->with('stocks')])->orderBy('name')->get()
            ->map(fn (Product $product): array => ['id' => $product->id, 'name' => $product->name, 'description' => $product->description, 'category' => $product->category?->name,
                'tags' => $product->tags->pluck('name'), 'currency' => $product->currency, 'price' => Money::fromMinor($product->base_price_minor),
                'image_url' => $product->image_path ? $baseUrl.'/store/products/'.$product->id.'/image?v='.$product->updated_at->timestamp : null,
                'variants' => $product->variants->map(fn ($variant): array => ['id' => $variant->id, 'name' => $variant->name, 'price' => Money::fromMinor($variant->price_minor ?? $product->base_price_minor),
                    'attributes' => $variant->attributes ?? [], 'track_inventory' => $product->track_inventory,
                    'availability' => $variant->stocks->whereNotNull('branch_id')->mapWithKeys(fn (InventoryStock $stock) => [(string) $stock->branch_id => max(0, $stock->on_hand - $stock->reserved)])]),
            ]),
            'categories' => $academy->productCategories()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'slug']),
            'locations' => $academy->branches()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'bank_details' => ['bank_name' => $academy->bank_name, 'account_name' => $academy->bank_account_name, 'account_number' => $academy->bank_account_number],
            'paystack_ready' => filled(config('services.paystack.secret_key'))];
    }

    /** @return array{AcademyWebsite, array<string, mixed>, StoreOrder} */
    private function authorizedOrder(Request $request, string $subdomain, string $token): array
    {
        [$website, $common] = $this->resolve($request, $subdomain);
        $order = StoreOrder::query()->where('academy_id', $website->academy_id)
            ->where('public_token_hash', hash('sha256', $token))->firstOrFail();

        return [$website, $common, $order];
    }

    private function paymentResponse(Request $request, string $subdomain, string $token, StoreOrderPayment $payment): JsonResponse
    {
        $route = $request->routeIs('public.tenant.*') ? 'public.tenant.store-payment-verify' : 'public.academy.store-payment-verify';

        return response()->json([
            'access_code' => $payment->access_code,
            'reference' => $payment->reference,
            'verify_url' => route($route, ['subdomain' => $subdomain, 'token' => $token, 'payment' => $payment->id]),
        ]);
    }

    /** @return array{AcademyWebsite, array<string, mixed>} */
    private function resolve(Request $request, string $subdomain): array
    {
        $website = AcademyWebsite::query()->where('subdomain', $subdomain)->where('is_published', true)->with('academy')->firstOrFail();
        $sections = AcademyWebsiteDefaults::merge($website->sections);
        abort_unless(($sections['store']['enabled'] ?? false) === true, 404);
        $academy = $website->academy;
        $base = $request->routeIs('public.tenant.*') ? '' : '/sites/'.$subdomain;

        return [$website, ['academy' => ['name' => $academy->name, 'type' => $academy->academy_type, 'logo_url' => $academy->logo_path ? $base.'/logo' : null,
            'member_singular' => $academy->member_label_singular, 'member_plural' => $academy->member_label_plural, 'currency' => $academy->currency,
            'theme' => AccessibleColor::theme($academy->primary_color, $academy->secondary_color)],
            'site' => ['subdomain' => $subdomain, 'theme_key' => $website->theme_key, 'sections' => $sections], 'base_url' => $base]];
    }
}
