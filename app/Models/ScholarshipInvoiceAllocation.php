<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['academy_id', 'scholarship_award_id', 'academy_invoice_id', 'funding_type', 'amount_minor', 'funded_minor', 'status', 'created_by_user_id'])]
class ScholarshipInvoiceAllocation extends Model
{
    public function award(): BelongsTo { return $this->belongsTo(ScholarshipAward::class, 'scholarship_award_id'); }
    public function invoice(): BelongsTo { return $this->belongsTo(AcademyInvoice::class, 'academy_invoice_id'); }
    public function contributions(): HasMany { return $this->hasMany(SponsorContribution::class); }

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'funded_minor' => 'integer'];
    }
}
