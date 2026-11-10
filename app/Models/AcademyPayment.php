<?php

namespace App\Models;

use App\Enums\AcademyPaymentMethod;
use App\Enums\AcademyPaymentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $academy_id
 * @property string $reference
 * @property int|null $application_id
 * @property int|null $player_id
 * @property AcademyPaymentMethod $method
 * @property AcademyPaymentStatus $status
 * @property string $currency
 * @property int $amount_minor
 * @property Carbon $received_on
 */
#[Fillable([
    'academy_id', 'reference', 'application_id', 'player_id', 'sponsor_id', 'method', 'status', 'currency',
    'amount_minor', 'gross_amount_minor', 'customer_fee_minor', 'provider_fee_minor', 'unallocated_minor',
    'provider', 'reversed_minor', 'received_on', 'external_reference', 'notes', 'recorded_by_user_id',
])]
class AcademyPayment extends Model
{
    /** @return BelongsTo<Academy, $this> */
    public function academy(): BelongsTo
    {
        return $this->belongsTo(Academy::class);
    }

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

    /** @return BelongsTo<Sponsor, $this> */
    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(Sponsor::class);
    }

    /** @return HasOne<SponsorContribution, $this> */
    public function sponsorContribution(): HasOne
    {
        return $this->hasOne(SponsorContribution::class);
    }

    /** @return HasMany<AcademyPaymentAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(AcademyPaymentAllocation::class);
    }

    /** @return HasMany<AcademyPaymentAdjustment, $this> */
    public function adjustments(): HasMany
    {
        return $this->hasMany(AcademyPaymentAdjustment::class);
    }

    protected function casts(): array
    {
        return [
            'method' => AcademyPaymentMethod::class, 'status' => AcademyPaymentStatus::class,
            'amount_minor' => 'integer', 'gross_amount_minor' => 'integer', 'customer_fee_minor' => 'integer',
            'provider_fee_minor' => 'integer', 'unallocated_minor' => 'integer',
            'reversed_minor' => 'integer', 'received_on' => 'date',
        ];
    }
}
