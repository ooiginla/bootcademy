<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['academy_id', 'referral_id', 'academy_payment_id', 'academy_invoice_id', 'event_key', 'currency', 'qualifying_amount_minor', 'reward_amount_minor', 'status', 'earned_at', 'approved_at', 'approved_by_user_id', 'paid_at', 'paid_by_user_id', 'payout_reference', 'payout_notes'])]
class ReferralReward extends Model
{
    /** @return BelongsTo<Referral, $this> */
    public function referral(): BelongsTo
    {
        return $this->belongsTo(Referral::class);
    }

    /** @return BelongsTo<AcademyPayment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(AcademyPayment::class, 'academy_payment_id');
    }

    /** @return BelongsTo<AcademyInvoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(AcademyInvoice::class, 'academy_invoice_id');
    }

    /** @return HasOne<AcademyExpense, $this> */
    public function expense(): HasOne
    {
        return $this->hasOne(AcademyExpense::class);
    }

    protected function casts(): array
    {
        return ['qualifying_amount_minor' => 'integer', 'reward_amount_minor' => 'integer', 'earned_at' => 'datetime', 'approved_at' => 'datetime', 'paid_at' => 'datetime'];
    }
}
