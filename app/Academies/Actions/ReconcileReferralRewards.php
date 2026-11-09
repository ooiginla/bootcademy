<?php

namespace App\Academies\Actions;

use App\Models\Academy;
use App\Models\Referral;
use App\Models\ReferralReward;
use App\Models\ReferralSetting;

class ReconcileReferralRewards
{
    public function handle(Academy $academy): int
    {
        $setting = ReferralSetting::query()->where('academy_id', $academy->id)->where('is_enabled', true)->first();
        if ($setting === null) {
            return 0;
        }
        $created = 0;
        $academy->referrals()->where('status', 'attributed')->whereDoesntHave('rewards')->with(['application', 'referredPlayer'])->chunkById(100, function ($referrals) use ($academy, $setting, &$created): void {
            foreach ($referrals as $referral) {
                $event = match ($setting->qualifying_event) {
                    'enrollment' => $this->enrollmentEvent($referral, $academy->currency),
                    'programme_invoice_paid' => $this->paidInvoiceEvent($referral),
                    default => $this->firstPaymentEvent($referral, $setting->eligible_payment_scope),
                };
                if ($event === null) {
                    continue;
                }
                $amount = $setting->reward_type === 'fixed'
                    ? (int) $setting->fixed_reward_minor
                    : intdiv(((int) $event['base_minor'] * (int) $setting->reward_basis_points) + 5000, 10000);
                if ($amount <= 0) {
                    continue;
                }
                $reward = ReferralReward::query()->firstOrCreate(
                    ['academy_id' => $academy->id, 'event_key' => $event['key']],
                    ['referral_id' => $referral->id, 'academy_payment_id' => $event['payment_id'], 'academy_invoice_id' => $event['invoice_id'], 'currency' => $event['currency'], 'qualifying_amount_minor' => $event['base_minor'], 'reward_amount_minor' => $amount, 'status' => 'pending', 'earned_at' => now()],
                );
                if ($reward->wasRecentlyCreated) {
                    $referral->update(['status' => 'qualified', 'qualified_at' => now()]);
                    $created++;
                }
            }
        });

        return $created;
    }

    private function enrollmentEvent(Referral $referral, string $currency): ?array
    {
        $playerId = $referral->referred_player_id ?? $referral->application?->converted_player_id;
        if ($playerId === null || ! $referral->application?->enrolled_at && ! $referral->referredPlayer?->enrollments()->where('status', 'active')->exists()) {
            return null;
        }

        return ['key' => 'referral-'.$referral->id.'-enrollment', 'base_minor' => 0, 'currency' => $currency, 'payment_id' => null, 'invoice_id' => null];
    }

    private function firstPaymentEvent(Referral $referral, string $scope): ?array
    {
        $query = $referral->application?->payments() ?? $referral->referredPlayer?->payments();
        if ($query === null) {
            return null;
        }
        $payment = $query->where('status', 'verified')->whereRaw('amount_minor > reversed_minor')
            ->when($scope === 'programme', fn ($query) => $query->whereHas('allocations.invoice', fn ($query) => $query->where('purpose', 'programme')))
            ->oldest('received_on')->oldest('id')->first();
        if ($payment === null) {
            return null;
        }

        return ['key' => 'referral-'.$referral->id.'-payment-'.$payment->id, 'base_minor' => $payment->amount_minor - $payment->reversed_minor, 'currency' => $payment->currency, 'payment_id' => $payment->id, 'invoice_id' => null];
    }

    private function paidInvoiceEvent(Referral $referral): ?array
    {
        $query = $referral->application?->invoices() ?? $referral->referredPlayer?->invoices();
        $invoice = $query?->where('purpose', 'programme')->where('status', 'paid')->oldest('issued_at')->first();
        if ($invoice === null) {
            return null;
        }

        return ['key' => 'referral-'.$referral->id.'-invoice-'.$invoice->id, 'base_minor' => $invoice->paid_minor, 'currency' => $invoice->currency, 'payment_id' => null, 'invoice_id' => $invoice->id];
    }
}
