<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['academy_id', 'academy_cycle_id', 'academy_cycle_term_id', 'cohort_id', 'term_scope_id', 'cohort_scope_id', 'name', 'category', 'currency', 'amount_minor', 'is_compulsory', 'applies_to_all_programmes', 'is_active', 'created_by_user_id'])]
class AcademyCycleFee extends Model
{
    /** @return BelongsTo<Academy, $this> */
    public function academy(): BelongsTo
    {
        return $this->belongsTo(Academy::class);
    }

    /** @return BelongsTo<AcademyCycle, $this> */
    public function cycle(): BelongsTo
    {
        return $this->belongsTo(AcademyCycle::class, 'academy_cycle_id');
    }

    /** @return BelongsTo<AcademyCycleTerm, $this> */
    public function term(): BelongsTo
    {
        return $this->belongsTo(AcademyCycleTerm::class, 'academy_cycle_term_id');
    }

    /** @return BelongsTo<Cohort, $this> */
    public function cohort(): BelongsTo
    {
        return $this->belongsTo(Cohort::class);
    }

    /** @return BelongsToMany<Programme, $this> */
    public function programmes(): BelongsToMany
    {
        return $this->belongsToMany(Programme::class, 'academy_cycle_fee_programme')->withPivot('academy_id')->withTimestamps();
    }

    /** @return HasMany<AcademyInvoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(AcademyInvoice::class);
    }

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'is_compulsory' => 'boolean', 'applies_to_all_programmes' => 'boolean', 'is_active' => 'boolean'];
    }
}
