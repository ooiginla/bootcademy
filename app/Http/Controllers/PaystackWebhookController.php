<?php

namespace App\Http\Controllers;

use App\Commerce\SettleStoreOrderPayment;
use App\Models\OnlinePaymentIntent;
use App\Models\StoreOrderPayment;
use App\Payments\PaystackWebhookSignature;
use App\Payments\SettleOnlinePayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaystackWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        PaystackWebhookSignature $signature,
        SettleOnlinePayment $settle,
        SettleStoreOrderPayment $settleStorePayment,
    ): JsonResponse {
        abort_unless($signature->valid($request), 401);
        if ($request->input('event') !== 'charge.success') {
            return response()->json(['received' => true]);
        }
        $reference = $request->input('data.reference');
        if (! is_string($reference)) {
            return response()->json(['received' => true]);
        }
        $intent = OnlinePaymentIntent::query()->where('provider', 'paystack')->where('reference', $reference)->first();
        if ($intent !== null) {
            $settle->handle($intent, (array) $request->input('data', []), $request);
        } else {
            $storePayment = StoreOrderPayment::query()->where('method', 'paystack')->where('reference', $reference)->first();
            if ($storePayment !== null) {
                $settleStorePayment->handle($storePayment, (array) $request->input('data', []), $request);
            }
        }

        return response()->json(['received' => true]);
    }
}
