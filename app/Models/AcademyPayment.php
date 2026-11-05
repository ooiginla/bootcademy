<?php

namespace App\Models;

use App\Enums\AcademyPaymentMethod;
use App\Enums\AcademyPaymentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
    'academy_id', 'reference', 'application_id', 'player_id', 'method', 'status', 'currency',
    'amount_minor', 'reversed_minor', 'received_on', 'external_reference', 'notes', 'recorded_by_user_id',
])]
class AcademyPayment extends Model
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

    /** @return HasMany<AcademyPaymentAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(AcademyPaymentAllocation::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(AcademyPaymentAdjustment::class);
    }

    protected function casts(): array
    {
        return [
            'method' => AcademyPaymentMethod::class, 'status' => AcademyPaymentStatus::class,
            'amount_minor' => 'integer', 'reversed_minor' => 'integer', 'received_on' => 'date',
        ];
    }
}
