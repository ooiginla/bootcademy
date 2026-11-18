<?php

namespace App\Platform\Subscriptions;

use App\Models\PlatformSetting;
use App\Models\User;
use App\Payments\Contracts\PaymentProvider;
use App\Platform\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Saves the platform's own bank account and keeps its Paystack subaccount in step.
 *
 * Subscription payments settle the plan price to this subaccount. Clearing the account stops
 * that, and subscription payments then stay in the main Paystack account.
 */
class UpdatePlatformSettlementAccount
{
    public function __construct(
        private readonly PaymentProvider $provider,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(?string $bankCode, ?string $accountNumber, User $actor, Request $request): PlatformSetting
    {
        $settings = PlatformSetting::current();
        if ($bankCode === null || $accountNumber === null) {
            return $this->clear($settings, $actor, $request);
        }

        try {
            $bank = collect($this->provider->banks())->firstWhere('code', $bankCode);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['bank_code' => $exception->getMessage()]);
        }
        if ($bank === null) {
            throw ValidationException::withMessages(['bank_code' => 'Select a bank from the list.']);
        }
        try {
            $accountName = $this->provider->resolveAccount($accountNumber, $bankCode);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['bank_account_number' => $exception->getMessage()]);
        }

        $settings->update([
            'bank_code' => $bankCode,
            'bank_name' => $bank['name'],
            'bank_account_name' => $accountName,
            'bank_account_number' => $accountNumber,
        ]);
        try {
            $result = $this->provider->saveSubaccount(
                $settings->paystack_subaccount_code,
                (string) config('app.name').' subscriptions',
                $bankCode,
                $accountNumber,
                $actor->email,
            );
            $settings->update([
                'paystack_subaccount_code' => $result['subaccount_code'],
                'paystack_subaccount_status' => $result['active'] ? 'active' : 'inactive',
                'paystack_subaccount_error' => null,
                'paystack_subaccount_synced_at' => now(),
            ]);
        } catch (RuntimeException $exception) {
            $settings->update([
                'paystack_subaccount_status' => $settings->paystack_subaccount_code === null ? 'failed' : $settings->paystack_subaccount_status,
                'paystack_subaccount_error' => mb_substr($exception->getMessage(), 0, 500),
            ]);
        }
        $this->audit->record('platform.settlement_account.updated', $actor, null, $settings, [
            'bank_code' => $bankCode,
            'account_number_last4' => substr($accountNumber, -4),
            'subaccount_status' => $settings->paystack_subaccount_status,
            'error' => $settings->paystack_subaccount_error,
        ], $request);

        return $settings;
    }

    private function clear(PlatformSetting $settings, User $actor, Request $request): PlatformSetting
    {
        if ($settings->paystack_subaccount_code !== null && $settings->bank_code !== null && $settings->bank_account_number !== null) {
            try {
                $this->provider->saveSubaccount($settings->paystack_subaccount_code, (string) config('app.name').' subscriptions', $settings->bank_code, $settings->bank_account_number, null, false);
            } catch (RuntimeException) {
                // Settlement is switched off locally either way.
            }
        }
        $settings->update([
            'bank_code' => null, 'bank_name' => null, 'bank_account_name' => null, 'bank_account_number' => null,
            'paystack_subaccount_status' => $settings->paystack_subaccount_code === null ? 'none' : 'inactive',
            'paystack_subaccount_error' => null,
        ]);
        $this->audit->record('platform.settlement_account.updated', $actor, null, $settings, ['configured' => false], $request);

        return $settings;
    }
}
