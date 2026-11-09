<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $academy_id
 * @property int $programme_cycle_id
 * @property int|null $academy_cycle_term_id
 * @property int|null $cohort_id
 * @property int $term_scope_id
 * @property int $cohort_scope_id
 * @property string $name
 * @property string|null $description
 * @property string $currency
 * @property int $total_minor
 * @property bool $is_default
 * @property bool $is_active
 * @property bool $is_legacy
 * @property-read Collection<int, ProgrammeCyclePaymentPlanItem> $items
 * @property-read Collection<int, ProgrammeCyclePaymentPlanInstallment> $installments
 */
#[Fillable([
    'academy_id', 'programme_cycle_id', 'academy_cycle_term_id', 'cohort_id', 'term_scope_id', 'cohort_scope_id',
    'name', 'description', 'currency', 'total_minor', 'is_default', 'is_active', 'is_legacy', 'created_by_user_id',
])]
class ProgrammeCyclePaymentPlan extends Model
{
    /** @return BelongsTo<Academy, $this> */
    public function academy(): BelongsTo
    {
        return $this->belongsTo(Academy::class);
    }

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

    /** @return HasMany<ProgrammeCyclePaymentPlanItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(ProgrammeCyclePaymentPlanItem::class);
    }

    /** @return HasMany<ProgrammeCyclePaymentPlanInstallment, $this> */
    public function installments(): HasMany
    {
        return $this->hasMany(ProgrammeCyclePaymentPlanInstallment::class);
    }

    protected function casts(): array
    {
        return [
            'total_minor' => 'integer', 'term_scope_id' => 'integer', 'cohort_scope_id' => 'integer',
            'is_default' => 'boolean', 'is_active' => 'boolean', 'is_legacy' => 'boolean',
        ];
    }
}
