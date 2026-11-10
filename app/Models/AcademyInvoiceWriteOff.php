<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['academy_id', 'academy_invoice_id', 'amount_minor', 'reason', 'created_by_user_id'])]
class AcademyInvoiceWriteOff extends Model
{
    /** @return BelongsTo<Academy, $this> */
    public function academy(): BelongsTo
    {
        return $this->belongsTo(Academy::class);
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
        return ['amount_minor' => 'integer'];
    }
}
