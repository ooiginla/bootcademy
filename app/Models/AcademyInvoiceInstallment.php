<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property int $amount_minor
 * @property int $paid_minor
 * @property Carbon|null $due_on
 * @property int|null $due_offset_days
 * @property int $sequence
 */
#[Fillable(['academy_id', 'academy_invoice_id', 'name', 'amount_minor', 'paid_minor', 'written_off_minor', 'due_on', 'due_offset_days', 'sequence'])]
class AcademyInvoiceInstallment extends Model
{
    /** @return BelongsTo<AcademyInvoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(AcademyInvoice::class, 'academy_invoice_id');
    }

    /** @return HasMany<AcademyPaymentAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(AcademyPaymentAllocation::class, 'academy_invoice_installment_id');
    }

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer', 'paid_minor' => 'integer', 'written_off_minor' => 'integer', 'due_on' => 'date',
            'due_offset_days' => 'integer', 'sequence' => 'integer',
        ];
    }
}
