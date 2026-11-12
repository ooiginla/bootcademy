<?php

namespace App\Models;

use App\Enums\PlayerStatus;
use Database\Factories\PlayerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $academy_id
 * @property string|null $internal_identifier
 * @property string $first_name
 * @property string|null $middle_name
 * @property string $last_name
 * @property Carbon|null $date_of_birth
 * @property string|null $gender
 * @property string|null $phone
 * @property string|null $whatsapp_number
 * @property string|null $home_address
 * @property string|null $city
 * @property string|null $state_region
 * @property string|null $country
 * @property PlayerStatus $status
 * @property-read Collection<int, Guardian> $guardians
 * @property-read Collection<int, Enrollment> $enrollments
 * @property-read Collection<int, PlayerCustomFieldValue> $customFieldValues
 */
#[Fillable([
    'academy_id', 'internal_identifier', 'first_name', 'middle_name', 'last_name',
    'date_of_birth', 'gender', 'phone', 'whatsapp_number', 'home_address',
    'city', 'state_region', 'country', 'status',
])]
class Player extends Model
{
    /** @use HasFactory<PlayerFactory> */
    use HasFactory, SoftDeletes;

    /** @return BelongsTo<Academy, $this> */
    public function academy(): BelongsTo
    {
        return $this->belongsTo(Academy::class);
    }

    /** @return BelongsToMany<Guardian, $this> */
    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class, 'player_guardian')
            ->withPivot(['academy_id', 'relationship', 'is_primary', 'is_emergency_contact', 'is_authorized_pickup'])
            ->withTimestamps();
    }

    /** @return HasMany<Enrollment, $this> */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /** @return HasMany<AcademyInvoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(AcademyInvoice::class);
    }

    /** @return HasMany<AcademyPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(AcademyPayment::class);
    }

    /** @return HasMany<PlayerCustomFieldValue, $this> */
    public function customFieldValues(): HasMany
    {
        return $this->hasMany(PlayerCustomFieldValue::class);
    }

    protected function casts(): array
    {
        return ['date_of_birth' => 'date', 'status' => PlayerStatus::class];
    }
}
