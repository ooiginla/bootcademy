<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['academy_id', 'sponsor_id', 'academy_cycle_id', 'academy_cycle_term_id', 'programme_id', 'branch_id', 'name', 'funding_type', 'coverage_type', 'percentage_basis_points', 'fixed_amount_minor', 'currency', 'starts_on', 'ends_on', 'is_active', 'description', 'created_by_user_id'])]
class ScholarshipScheme extends Model
{
    public function sponsor(): BelongsTo { return $this->belongsTo(Sponsor::class); }
    public function cycle(): BelongsTo { return $this->belongsTo(AcademyCycle::class, 'academy_cycle_id'); }
    public function term(): BelongsTo { return $this->belongsTo(AcademyCycleTerm::class, 'academy_cycle_term_id'); }
    public function programme(): BelongsTo { return $this->belongsTo(Programme::class); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function awards(): HasMany { return $this->hasMany(ScholarshipAward::class); }

    protected function casts(): array
    {
        return [
            'percentage_basis_points' => 'integer', 'fixed_amount_minor' => 'integer',
            'starts_on' => 'date', 'ends_on' => 'date', 'is_active' => 'boolean',
        ];
    }
}
