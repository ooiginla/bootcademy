<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $academy_id
 * @property int $academy_cycle_id
 * @property string $name
 * @property Carbon|null $starts_on
 * @property Carbon|null $ends_on
 */
#[Fillable(['academy_id', 'academy_cycle_id', 'name', 'starts_on', 'ends_on', 'sort_order', 'is_default', 'is_active'])]
class AcademyCycleTerm extends Model
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

    /** @return HasMany<ProgrammeCycleFeeItem, $this> */
    public function programmeFeeItems(): HasMany
    {
        return $this->hasMany(ProgrammeCycleFeeItem::class);
    }

    /** @return HasMany<AcademyCycleFee, $this> */
    public function cycleFees(): HasMany
    {
        return $this->hasMany(AcademyCycleFee::class);
    }

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'sort_order' => 'integer', 'is_default' => 'boolean', 'is_active' => 'boolean'];
    }
}
