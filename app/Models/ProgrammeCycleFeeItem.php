<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['academy_id', 'programme_cycle_id', 'academy_cycle_term_id', 'cohort_id', 'name', 'amount_minor', 'sort_order'])]
class ProgrammeCycleFeeItem extends Model
{
    /** @return BelongsTo<ProgrammeCycle, $this> */
    public function programmeCycle(): BelongsTo
    {
        return $this->belongsTo(ProgrammeCycle::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(AcademyCycleTerm::class, 'academy_cycle_term_id');
    }

    public function cohort(): BelongsTo
    {
        return $this->belongsTo(Cohort::class);
    }
}
