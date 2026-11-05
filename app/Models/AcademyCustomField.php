<?php

namespace App\Models;

use App\Enums\CustomFieldType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $academy_id
 * @property string $entity_type
 * @property string $key
 * @property string $label
 * @property CustomFieldType $type
 * @property list<string>|null $options
 * @property bool $is_required
 * @property bool $is_sensitive
 * @property bool $is_active
 * @property int $sort_order
 */
#[Fillable(['academy_id', 'entity_type', 'key', 'label', 'type', 'options', 'is_required', 'is_sensitive', 'is_active', 'sort_order'])]
class AcademyCustomField extends Model
{
    /** @return BelongsTo<Academy, $this> */
    public function academy(): BelongsTo
    {
        return $this->belongsTo(Academy::class);
    }

    /** @return HasMany<PlayerCustomFieldValue, $this> */
    public function playerValues(): HasMany
    {
        return $this->hasMany(PlayerCustomFieldValue::class, 'custom_field_id');
    }

    /** @return HasMany<CoachCustomFieldValue, $this> */
    public function coachValues(): HasMany
    {
        return $this->hasMany(CoachCustomFieldValue::class, 'custom_field_id');
    }

    protected function casts(): array
    {
        return [
            'type' => CustomFieldType::class,
            'options' => 'array',
            'is_required' => 'boolean',
            'is_sensitive' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
