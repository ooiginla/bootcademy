<?php

namespace App\Payments;

use App\Commerce\SettleStoreOrderPayment;
use App\Models\OnlinePaymentIntent;
use App\Models\StoreOrderPayment;
use App\Payments\Contracts\PaymentProvider;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Confirms unfinished Paystack payments by asking Paystack directly, in place of a webhook.
 *
 * Payments younger than an hour are checked on every run (every five minutes), then hourly.
 * After two days an attempt Paystack still reports as abandoned is closed as expired.
 */
class RequeryPendingPayments
{
    private const int GIVE_UP_AFTER_HOURS = 48;

    public function __construct(
        private readonly PaymentProvider $provider,
        private readonly SettleOnlinePayment $settleApplication,
        private readonly SettleStoreOrderPayment $settleStore,
    ) {}

    /** @return array{checked: int, verified: int, expired: int} */
    public function handle(): array
    {
        $totals = ['checked' => 0, 'verified' => 0, 'expired' => 0];
        $request = Request::create('/internal/payments/requery', 'POST');

        $this->due(OnlinePaymentIntent::query()->where('provider', 'paystack'))
            ->each(function (OnlinePaymentIntent $intent) use (&$totals, $request): void {
                $this->check($intent, fn (array $data) => $this->settleApplication->handle($intent, $data, $request), $totals);
            });
        $this->due(StoreOrderPayment::query()->where('method', 'paystack'))
            ->each(function (StoreOrderPayment $payment) use (&$totals, $request): void {
                $this->check($payment, fn (array $data) => $this->settleStore->handle($payment, $data, $request), $totals);
            });

        return $totals;
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Collection<int, TModel>
     */
    private function due($query)
    {
        $now = now();

        return $query->where('status', 'pending')
            ->whereNotNull('access_code')
            ->where('created_at', '>=', $now->copy()->subHours(self::GIVE_UP_AFTER_HOURS + 24))
            ->where(fn ($query) => $query->whereNull('last_checked_at')
                ->orWhere('created_at', '>=', $now->copy()->subHour())
                ->orWhere('last_checked_at', '<=', $now->copy()->subHour()))
            ->orderBy('id')->limit(200)->get()->toBase();
    }

    /**
     * @param  callable(array<string, mixed>): Model  $settle
     * @param  array{checked: int, verified: int, expired: int}  $totals
     */
    private function check(OnlinePaymentIntent|StoreOrderPayment $record, callable $settle, array &$totals): void
    {
        $totals['checked']++;
        try {
            $data = $this->provider->verify($record->reference);
            $settled = $settle($data);
        } catch (RuntimeException $exception) {
            Log::warning('Paystack requery failed.', ['reference' => $record->reference, 'error' => $exception->getMessage()]);
            $record->forceFill(['last_checked_at' => now()])->save();

            return;
        }
        $settled->forceFill(['last_checked_at' => now()])->save();
        if ($settled->getAttribute('status') === 'verified') {
            $totals['verified']++;
        } elseif ($settled->getAttribute('status') === 'pending'
            && ($data['status'] ?? null) === 'abandoned'
            && $record->created_at !== null
            && $record->created_at->lt(now()->subHours(self::GIVE_UP_AFTER_HOURS))) {
            $settled->forceFill(['status' => 'expired', 'failure_reason' => 'Payment was not completed within 48 hours.'])->save();
            $totals['expired']++;
        }
    }
}
