<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $academy_id
 * @property int $player_id
 * @property int $custom_field_id
 * @property mixed $value
 * @property-read AcademyCustomField $field
 */
#[Fillable(['academy_id', 'player_id', 'custom_field_id', 'value'])]
class PlayerCustomFieldValue extends Model
{
    /** @return BelongsTo<Player, $this> */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
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
