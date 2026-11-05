<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $amount_minor
 * @property Carbon|null $due_on
 * @property int|null $due_offset_days
 */
#[Fillable(['academy_id', 'programme_cycle_id', 'name', 'amount_minor', 'due_on', 'due_offset_days', 'sequence'])]
class ProgrammeCycleInstallment extends Model
{
    /** @return BelongsTo<ProgrammeCycle, $this> */
    public function programmeCycle(): BelongsTo
    {
        return $this->belongsTo(ProgrammeCycle::class);
    }

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'due_on' => 'date', 'due_offset_days' => 'integer', 'sequence' => 'integer'];
    }
}
