<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $academy_id
 * @property int $programme_cycle_id
 * @property int|null $cohort_id
 * @property string $name
 * @property int $amount_minor
 * @property int $sort_order
 */
#[Fillable(['academy_id', 'programme_cycle_id', 'academy_cycle_term_id', 'cohort_id', 'name', 'amount_minor', 'sort_order'])]
class ProgrammeCycleFeeItem extends Model
{
    /** @return BelongsTo<ProgrammeCycle, $this> */
    public function programmeCycle(): BelongsTo
    {
        return $this->belongsTo(ProgrammeCycle::class);
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
}
