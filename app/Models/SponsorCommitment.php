<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['academy_id', 'sponsor_id', 'academy_cycle_id', 'name', 'agreement_reference', 'benefits', 'currency', 'committed_minor', 'received_minor', 'starts_on', 'ends_on', 'renewal_on', 'status', 'notes', 'created_by_user_id'])]
class SponsorCommitment extends Model
{
    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(Sponsor::class);
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(AcademyCycle::class, 'academy_cycle_id');
    }

    public function awards(): HasMany
    {
        return $this->hasMany(ScholarshipAward::class);
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(SponsorContribution::class);
    }

    protected function casts(): array
    {
        return [
            'committed_minor' => 'integer', 'received_minor' => 'integer',
            'starts_on' => 'date', 'ends_on' => 'date', 'renewal_on' => 'date',
        ];
    }
}
