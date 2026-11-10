<?php

namespace App\Models;

use App\Enums\EnrollmentPaymentRule;
use App\Enums\ProgrammeCyclePaymentMode;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $academy_id
 * @property int $academy_cycle_id
 * @property int $programme_id
 * @property int|null $branch_id
 * @property int $branch_scope_id
 * @property string $fee_name
 * @property string $currency
 * @property int $application_fee_minor
 * @property ProgrammeCyclePaymentMode $payment_mode
 * @property EnrollmentPaymentRule $enrollment_payment_rule
 * @property bool $is_active
 * @property-read AcademyCycle $cycle
 * @property-read Programme $programme
 * @property-read Branch|null $branch
 */
#[Fillable([
    'academy_id', 'academy_cycle_id', 'programme_id', 'fee_name', 'branch_id', 'branch_scope_id', 'currency', 'application_fee_minor',
    'payment_mode', 'enrollment_payment_rule', 'is_active',
])]
class ProgrammeCycle extends Model
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

    /** @return BelongsTo<Programme, $this> */
    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return HasMany<ProgrammeCycleFeeItem, $this> */
    public function feeItems(): HasMany
    {
        return $this->hasMany(ProgrammeCycleFeeItem::class);
    }

    /** @return HasMany<ProgrammeCycleInstallment, $this> */
    public function installments(): HasMany
    {
        return $this->hasMany(ProgrammeCycleInstallment::class);
    }

    /** @return HasMany<ProgrammeCyclePaymentPlan, $this> */
    public function paymentPlans(): HasMany
    {
        return $this->hasMany(ProgrammeCyclePaymentPlan::class);
    }

    /** @return HasMany<Enrollment, $this> */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /** @return HasMany<Cohort, $this> */
    public function cohorts(): HasMany
    {
        return $this->hasMany(Cohort::class);
    }

    /** @return HasMany<ApplicationIntake, $this> */
    public function applicationIntakes(): HasMany
    {
        return $this->hasMany(ApplicationIntake::class);
    }

    public function totalFeeMinor(): int
    {
        return (int) $this->feeItems()->sum('amount_minor');
    }

    protected function casts(): array
    {
        return [
            'application_fee_minor' => 'integer', 'payment_mode' => ProgrammeCyclePaymentMode::class,
            'enrollment_payment_rule' => EnrollmentPaymentRule::class, 'is_active' => 'boolean',
        ];
    }
}
