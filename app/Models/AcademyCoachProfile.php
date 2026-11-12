<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $academy_id
 * @property int $membership_id
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
 * @property int|null $years_of_experience
 * @property string|null $biography
 */
#[Fillable([
    'academy_id', 'membership_id', 'first_name', 'middle_name', 'last_name',
    'date_of_birth', 'gender', 'phone', 'whatsapp_number', 'home_address',
    'city', 'state_region', 'country', 'years_of_experience', 'biography',
])]
class AcademyCoachProfile extends Model
{
    use SoftDeletes;

    /** @return BelongsTo<Academy, $this> */
    public function academy(): BelongsTo
    {
        return $this->belongsTo(Academy::class);
    }

    /** @return BelongsTo<AcademyMembership, $this> */
    public function membership(): BelongsTo
    {
        return $this->belongsTo(AcademyMembership::class, 'membership_id');
    }

    /** @return HasMany<CoachCustomFieldValue, $this> */
    public function customFieldValues(): HasMany
    {
        return $this->hasMany(CoachCustomFieldValue::class, 'coach_profile_id');
    }

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'years_of_experience' => 'integer',
        ];
    }
}
