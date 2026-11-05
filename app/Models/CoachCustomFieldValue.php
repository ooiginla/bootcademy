<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $academy_id
 * @property int $coach_profile_id
 * @property int $custom_field_id
 * @property mixed $value
 * @property-read AcademyCustomField $field
 */
#[Fillable(['academy_id', 'coach_profile_id', 'custom_field_id', 'value'])]
class CoachCustomFieldValue extends Model
{
    /** @return BelongsTo<AcademyCoachProfile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(AcademyCoachProfile::class, 'coach_profile_id');
    }

    /** @return BelongsTo<AcademyCustomField, $this> */
    public function field(): BelongsTo
    {
        return $this->belongsTo(AcademyCustomField::class, 'custom_field_id');
    }

    protected function casts(): array
    {
        return ['value' => 'json'];
    }
}
