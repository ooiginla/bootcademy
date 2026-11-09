<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['academy_id', 'sponsor_commitment_id', 'scholarship_invoice_allocation_id', 'academy_payment_id', 'currency', 'amount_minor', 'reversed_minor', 'received_on', 'external_reference', 'notes', 'recorded_by_user_id'])]
class SponsorContribution extends Model
{
    public function commitment(): BelongsTo { return $this->belongsTo(SponsorCommitment::class, 'sponsor_commitment_id'); }
    public function allocation(): BelongsTo { return $this->belongsTo(ScholarshipInvoiceAllocation::class, 'scholarship_invoice_allocation_id'); }
    public function payment(): BelongsTo { return $this->belongsTo(AcademyPayment::class, 'academy_payment_id'); }

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'reversed_minor' => 'integer', 'received_on' => 'date'];
    }
}
