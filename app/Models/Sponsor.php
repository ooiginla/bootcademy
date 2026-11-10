<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['academy_id', 'name', 'type', 'contact_name', 'email', 'phone', 'address', 'notes', 'status', 'created_by_user_id'])]
class Sponsor extends Model
{
    /** @return HasMany<SponsorCommitment, $this> */
    public function commitments(): HasMany
    {
        return $this->hasMany(SponsorCommitment::class);
    }

    /** @return HasMany<ScholarshipScheme, $this> */
    public function schemes(): HasMany
    {
        return $this->hasMany(ScholarshipScheme::class);
    }
}
