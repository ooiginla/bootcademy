<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['academy_id', 'academy_cycle_id', 'name', 'starts_on', 'ends_on', 'sort_order', 'is_default', 'is_active'])]
class AcademyCycleTerm extends Model
{
    public function academy(): BelongsTo
    {
        return $this->belongsTo(Academy::class);
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(AcademyCycle::class, 'academy_cycle_id');
    }

    public function programmeFeeItems(): HasMany
    {
        return $this->hasMany(ProgrammeCycleFeeItem::class);
    }

    public function cycleFees(): HasMany
    {
        return $this->hasMany(AcademyCycleFee::class);
    }

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'sort_order' => 'integer', 'is_default' => 'boolean', 'is_active' => 'boolean'];
    }
}
