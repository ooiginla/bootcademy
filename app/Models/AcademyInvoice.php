<?php

namespace App\Models;

use App\Enums\AcademyInvoicePurpose;
use App\Enums\AcademyInvoiceStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $academy_id
 * @property string $number
 * @property string|null $fee_name
 * @property int|null $application_id
 * @property int|null $player_id
 * @property int $academy_cycle_id
 * @property int|null $programme_cycle_id
 * @property int|null $programme_id
 * @property int|null $branch_id
 * @property AcademyInvoicePurpose $purpose
 * @property AcademyInvoiceStatus $status
 * @property string $currency
 * @property int $total_minor
 * @property int $paid_minor
 * @property int $written_off_minor
 * @property Carbon $issued_at
 * @property Carbon|null $due_on
 * @property-read Application|null $application
 * @property-read ProgrammeCycle|null $programmeCycle
 */
#[Fillable([
    'academy_id', 'number', 'fee_name', 'application_id', 'player_id', 'academy_cycle_id', 'programme_cycle_id',
    'programme_id', 'branch_id', 'purpose', 'status', 'currency', 'total_minor', 'paid_minor', 'written_off_minor',
    'academy_cycle_fee_id', 'issued_at', 'due_on', 'cancellation_reason', 'cancelled_at', 'cancelled_by_user_id', 'created_by_user_id',
])]
class AcademyInvoice extends Model
{
    /** @return BelongsTo<Application, $this> */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /** @return BelongsTo<Player, $this> */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
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

    /** @return BelongsTo<ProgrammeCycle, $this> */
    public function programmeCycle(): BelongsTo
    {
        return $this->belongsTo(ProgrammeCycle::class);
    }

    /** @return HasMany<AcademyInvoiceItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(AcademyInvoiceItem::class);
    }

    /** @return HasMany<AcademyInvoiceInstallment, $this> */
    public function installments(): HasMany
    {
        return $this->hasMany(AcademyInvoiceInstallment::class);
    }

    /** @return HasMany<AcademyPaymentAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(AcademyPaymentAllocation::class);
    }

    public function writeOffs(): HasMany
    {
        return $this->hasMany(AcademyInvoiceWriteOff::class);
    }

    public function cycleFee(): BelongsTo
    {
        return $this->belongsTo(AcademyCycleFee::class, 'academy_cycle_fee_id');
    }

    public function balanceMinor(): int
    {
        return $this->total_minor - $this->paid_minor - $this->written_off_minor;
    }

    protected function casts(): array
    {
        return [
            'purpose' => AcademyInvoicePurpose::class, 'status' => AcademyInvoiceStatus::class,
            'total_minor' => 'integer', 'paid_minor' => 'integer', 'written_off_minor' => 'integer',
            'issued_at' => 'immutable_datetime', 'due_on' => 'date', 'cancelled_at' => 'immutable_datetime',
        ];
    }
}
