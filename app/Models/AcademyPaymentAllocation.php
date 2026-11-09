<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['academy_id', 'academy_payment_id', 'academy_invoice_id', 'academy_invoice_installment_id', 'scholarship_invoice_allocation_id', 'amount_minor'])]
class AcademyPaymentAllocation extends Model
{
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

    /** @return BelongsTo<AcademyInvoiceInstallment, $this> */
    public function installment(): BelongsTo
    {
        return $this->belongsTo(AcademyInvoiceInstallment::class, 'academy_invoice_installment_id');
    }

    public function adjustmentAllocations(): HasMany
    {
        return $this->hasMany(AcademyPaymentAdjustmentAllocation::class);
    }

    public function scholarshipAllocation(): BelongsTo
    {
        return $this->belongsTo(ScholarshipInvoiceAllocation::class, 'scholarship_invoice_allocation_id');
    }
}
