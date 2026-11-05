<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property string $description @property int $amount_minor */
#[Fillable(['academy_id', 'academy_invoice_id', 'description', 'amount_minor', 'sort_order'])]
class AcademyInvoiceItem extends Model
{
    /** @return BelongsTo<AcademyInvoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(AcademyInvoice::class, 'academy_invoice_id');
    }
}
