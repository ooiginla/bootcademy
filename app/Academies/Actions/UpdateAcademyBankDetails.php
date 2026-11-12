<?php

namespace App\Academies\Actions;

use App\Models\Academy;
use App\Models\User;
use App\Payments\Contracts\PaymentProvider;
use App\Platform\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Saves the academy's bank account and keeps its Paystack settlement subaccount in step.
 *
 * Online payments go to the subaccount, so they are only switched on once Paystack has accepted it.
 * If Paystack fails after the bank details are confirmed, the details are kept and the error is shown;
 * saving again retries.
 */
class UpdateAcademyBankDetails
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly PaymentProvider $provider,
    ) {}

    /** @param array<string, string|null> $data */
    public function handle(Academy $academy, User $actor, array $data, Request $request, bool $usesPaystack = false): void
    {
        if (! $usesPaystack) {
            DB::transaction(function () use ($academy, $actor, $data, $request): void {
                $academy->update(Arr::only($data, ['bank_name', 'bank_account_name', 'bank_account_number']) + ['bank_code' => null]);
                $this->audit->record('academy.bank_details.updated', $actor, $academy, $academy, [
                    'configured' => $academy->bank_account_number !== null,
                ], $request);
            });

            return;
        }

        $bankCode = $data['bank_code'] ?? null;
        $accountNumber = $data['bank_account_number'] ?? null;
        if ($bankCode === null || $accountNumber === null) {
            $this->clear($academy, $actor, $request);

            return;
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

        DB::transaction(function () use ($academy, $actor, $request, $bank, $bankCode, $accountNumber, $accountName): void {
            $academy->update([
                'bank_code' => $bankCode,
                'bank_name' => $bank['name'],
                'bank_account_name' => $accountName,
                'bank_account_number' => $accountNumber,
            ]);
            $this->audit->record('academy.bank_details.updated', $actor, $academy, $academy, [
                'configured' => true,
                'bank_code' => $bankCode,
                'account_number_last4' => substr($accountNumber, -4),
            ], $request);
        });

        $this->syncSubaccount($academy, $actor, $request);
    }

    public function syncSubaccount(Academy $academy, ?User $actor, Request $request): void
    {
        if ($academy->bank_code === null || $academy->bank_account_number === null) {
            return;
        }
        try {
            $result = $this->provider->saveSubaccount(
                $academy->paystack_subaccount_code,
                $academy->name,
                $academy->bank_code,
                $academy->bank_account_number,
                $academy->creator?->email,
            );
            $academy->update([
                'paystack_subaccount_code' => $result['subaccount_code'],
                'paystack_subaccount_status' => $result['active'] ? 'active' : 'inactive',
                'paystack_subaccount_error' => null,
                'paystack_subaccount_synced_at' => now(),
            ]);
        } catch (RuntimeException $exception) {
            $academy->update([
                'paystack_subaccount_status' => $academy->paystack_subaccount_code === null ? 'failed' : $academy->paystack_subaccount_status,
                'paystack_subaccount_error' => mb_substr($exception->getMessage(), 0, 500),
            ]);
        }
        $this->audit->record('academy.paystack_subaccount.synced', $actor, $academy, $academy, [
            'status' => $academy->paystack_subaccount_status,
            'subaccount_code' => $academy->paystack_subaccount_code,
            'error' => $academy->paystack_subaccount_error,
        ], $request);
    }

    private function clear(Academy $academy, User $actor, Request $request): void
    {
        if ($academy->paystack_subaccount_code !== null && $academy->bank_code !== null && $academy->bank_account_number !== null) {
            try {
                // Stop settlements to the old account; the subaccount stays on Paystack for history.
                $this->provider->saveSubaccount($academy->paystack_subaccount_code, $academy->name, $academy->bank_code, $academy->bank_account_number, null, false);
            } catch (RuntimeException) {
                // Online payment is switched off locally either way.
            }
        }
        DB::transaction(function () use ($academy, $actor, $request): void {
            $academy->update([
                'bank_code' => null, 'bank_name' => null, 'bank_account_name' => null, 'bank_account_number' => null,
                'paystack_subaccount_status' => $academy->paystack_subaccount_code === null ? 'none' : 'inactive',
                'paystack_subaccount_error' => null,
            ]);
            $this->audit->record('academy.bank_details.updated', $actor, $academy, $academy, ['configured' => false], $request);
        });
    }
}
