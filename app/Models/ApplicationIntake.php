<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[Fillable([
    'academy_id', 'programme_cycle_id', 'cohort_id', 'cohort_scope_id', 'branch_id',
    'branch_scope_id', 'opens_on', 'closes_on', 'is_enabled', 'created_by_user_id',
])]
class ApplicationIntake extends Model
{
    /** @return BelongsTo<ProgrammeCycle, $this> */
    public function programmeCycle(): BelongsTo
    {
        return $this->belongsTo(ProgrammeCycle::class);
    }

    /** @return BelongsTo<Cohort, $this> */
    public function cohort(): BelongsTo
    {
        return $this->belongsTo(Cohort::class);
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return HasMany<Application, $this> */
    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    /**
     * @param  Builder<ApplicationIntake>  $query
     * @return Builder<ApplicationIntake>
     */
    public function scopeOpenOn(Builder $query, Carbon|string $date): Builder
    {
        $date = Carbon::parse($date)->toDateString();

        return $query->where('is_enabled', true)
            ->where(fn (Builder $query) => $query->whereNull('opens_on')->orWhereDate('opens_on', '<=', $date))
            ->where(fn (Builder $query) => $query->whereNull('closes_on')->orWhereDate('closes_on', '>=', $date));
    }

    public function isOpenOn(Carbon|string $date): bool
    {
        $date = Carbon::parse($date)->startOfDay();

        return $this->is_enabled
            && ($this->opens_on === null || $this->opens_on->startOfDay()->lte($date))
            && ($this->closes_on === null || $this->closes_on->startOfDay()->gte($date));
    }

    protected function casts(): array
    {
        return [
            'opens_on' => 'date',
            'closes_on' => 'date',
            'is_enabled' => 'boolean',
            'cohort_scope_id' => 'integer',
            'branch_scope_id' => 'integer',
        ];
    }
}
