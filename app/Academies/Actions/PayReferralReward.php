<?php

namespace App\Academies\Actions;

use App\Models\Academy;
use App\Models\AcademyExpense;
use App\Models\ExpenseCategory;
use App\Models\ReferralReward;
use App\Models\User;
use App\Platform\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayReferralReward
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param array{payout_reference: string, payout_notes?: string|null} $data */
    public function handle(Academy $academy, ReferralReward $reward, User $actor, array $data, Request $request): ReferralReward
    {
        abort_unless($reward->academy_id === $academy->id, 404);

        return DB::transaction(function () use ($academy, $reward, $actor, $data, $request): ReferralReward {
            $reward = ReferralReward::query()->where('academy_id', $academy->id)
                ->whereKey($reward->id)->lockForUpdate()->with(['referral.referrer', 'referral.referredPlayer', 'referral.application'])->firstOrFail();
            if ($reward->status !== 'approved') {
                throw ValidationException::withMessages(['reward' => 'Approve this reward before recording payout.']);
            }
            $duplicateReference = ReferralReward::query()->where('academy_id', $academy->id)
                ->where('payout_reference', $data['payout_reference'])->whereKeyNot($reward->id)->exists();
            if ($duplicateReference) {
                throw ValidationException::withMessages(['payout_reference' => 'This payout reference has already been used.']);
            }

            $category = ExpenseCategory::query()->firstOrCreate(
                ['academy_id' => $academy->id, 'name' => 'Referral rewards'],
                ['is_active' => true],
            );
            if (! $category->is_active) {
                $category->update(['is_active' => true]);
            }
            $referrer = $reward->referral->referrer;
            $referredName = $reward->referral->referredPlayer
                ? trim($reward->referral->referredPlayer->first_name.' '.$reward->referral->referredPlayer->last_name)
                : trim(($reward->referral->application?->first_name ?? '').' '.($reward->referral->application?->last_name ?? ''));
            $referrerName = trim($referrer->first_name.' '.$referrer->last_name);
            $expense = AcademyExpense::query()->create([
                'academy_id' => $academy->id,
                'referral_reward_id' => $reward->id,
                'expense_category_id' => $category->id,
                'category' => 'referral_reward',
                'description' => 'Referral reward paid to '.$referrerName.' for referring '.$referredName,
                'vendor' => $referrerName,
                'reference' => $data['payout_reference'],
                'currency' => $reward->currency,
                'amount_minor' => $reward->reward_amount_minor,
                'incurred_on' => now($academy->timezone)->toDateString(),
                'created_by_user_id' => $actor->id,
            ]);
            $reward->update([
                'status' => 'paid', 'paid_at' => now(), 'paid_by_user_id' => $actor->id,
                'payout_reference' => $data['payout_reference'], 'payout_notes' => $data['payout_notes'] ?? null,
            ]);
            $this->audit->record('academy.referral_reward.paid', $actor, $academy, $reward, [
                'payout_reference' => $data['payout_reference'], 'expense_id' => $expense->id,
            ], $request);
            $this->audit->record('academy.expense.referral_reward_recorded', $actor, $academy, $expense, [
                'referral_reward_id' => $reward->id,
            ], $request);

            return $reward;
        });
    }
}
