<?php

namespace App\Models;

use App\Enums\AcademyCycleDurationUnit;
use App\Enums\AcademyCycleStatus;
use App\Enums\AcademyCycleType;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $academy_id
 * @property string $name
 * @property AcademyCycleType $type
 * @property Carbon|null $starts_on
 * @property Carbon|null $ends_on
 * @property int|null $duration_months
 * @property int|null $duration_value
 * @property AcademyCycleDurationUnit|null $duration_unit
 * @property AcademyCycleStatus $status
 */
#[Fillable(['academy_id', 'name', 'type', 'starts_on', 'ends_on', 'duration_months', 'duration_value', 'duration_unit', 'status'])]
class AcademyCycle extends Model
{
    public function supportsTerms(): bool
    {
        return $this->type === AcademyCycleType::Fixed;
    }

    public function rollingEndDate(CarbonInterface $activation): Carbon
    {
        $value = (int) $this->duration_value;
        $date = $activation->toMutable();

        return match ($this->duration_unit) {
            AcademyCycleDurationUnit::Days => $date->addDays($value),
            AcademyCycleDurationUnit::Weeks => $date->addWeeks($value),
            AcademyCycleDurationUnit::Months => $date->addMonthsNoOverflow($value),
            null => $date->addMonthsNoOverflow((int) $this->duration_months),
        };
    }

    public function maximumRollingDays(): int
    {
        $value = (int) $this->duration_value;

        return match ($this->duration_unit) {
            AcademyCycleDurationUnit::Days => $value,
            AcademyCycleDurationUnit::Weeks => $value * 7,
            AcademyCycleDurationUnit::Months => $value * 31,
            null => (int) $this->duration_months * 31,
        };
    }

    /** @return BelongsTo<Academy, $this> */
    public function academy(): BelongsTo
    {
        return $this->belongsTo(Academy::class);
    }

    /** @return HasMany<ProgrammeCycle, $this> */
    public function programmeCycles(): HasMany
    {
        return $this->hasMany(ProgrammeCycle::class);
    }

    /** @return HasMany<Enrollment, $this> */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /** @return HasMany<AcademyCycleFee, $this> */
    public function fees(): HasMany
    {
        return $this->hasMany(AcademyCycleFee::class);
    }

    /** @return HasMany<AcademyCycleTerm, $this> */
    public function terms(): HasMany
    {
        return $this->hasMany(AcademyCycleTerm::class);
    }

    protected function casts(): array
    {
        return [
            'type' => AcademyCycleType::class, 'status' => AcademyCycleStatus::class,
            'starts_on' => 'date', 'ends_on' => 'date', 'duration_months' => 'integer',
            'duration_value' => 'integer', 'duration_unit' => AcademyCycleDurationUnit::class,
        ];
    }
}
