<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['academy_id', 'academy_cycle_id', 'academy_invoice_write_off_id', 'referral_reward_id', 'expense_category_id', 'expense_vendor_id', 'branch_id', 'category', 'description', 'vendor', 'reference', 'currency', 'amount_minor', 'incurred_on', 'created_by_user_id'])]
class AcademyExpense extends Model
{
    use SoftDeletes;

    /** @return BelongsTo<Academy, $this> */
    public function academy(): BelongsTo
    {
        return $this->belongsTo(Academy::class);
    }

    /** @return BelongsTo<AcademyInvoiceWriteOff, $this> */
    public function writeOff(): BelongsTo
    {
        return $this->belongsTo(AcademyInvoiceWriteOff::class, 'academy_invoice_write_off_id');
    }

    /** @return BelongsTo<ReferralReward, $this> */
    public function referralReward(): BelongsTo
    {
        return $this->belongsTo(ReferralReward::class);
    }

    /** @return BelongsTo<ExpenseCategory, $this> */
    public function categoryDefinition(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    /** @return BelongsTo<ExpenseVendor, $this> */
    public function vendorDefinition(): BelongsTo
    {
        return $this->belongsTo(ExpenseVendor::class, 'expense_vendor_id');
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<AcademyCycle, $this> */
    public function cycle(): BelongsTo
    {
        return $this->belongsTo(AcademyCycle::class, 'academy_cycle_id');
    }

    /** @return HasMany<ExpenseAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(ExpenseAttachment::class);
    }

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'incurred_on' => 'date'];
    }
}
