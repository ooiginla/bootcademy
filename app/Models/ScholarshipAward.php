<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['academy_id', 'scholarship_scheme_id', 'player_id', 'enrollment_id', 'sponsor_commitment_id', 'status', 'starts_on', 'ends_on', 'notes', 'approved_at', 'approved_by_user_id'])]
class ScholarshipAward extends Model
{
    /** @return BelongsTo<ScholarshipScheme, $this> */
    public function scheme(): BelongsTo
    {
        return $this->belongsTo(ScholarshipScheme::class, 'scholarship_scheme_id');
    }

    /** @return BelongsTo<Player, $this> */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    /** @return BelongsTo<Enrollment, $this> */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /** @return BelongsTo<SponsorCommitment, $this> */
    public function commitment(): BelongsTo
    {
        return $this->belongsTo(SponsorCommitment::class, 'sponsor_commitment_id');
    }

    /** @return HasMany<ScholarshipInvoiceAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(ScholarshipInvoiceAllocation::class);
    }

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'approved_at' => 'immutable_datetime'];
    }
}
