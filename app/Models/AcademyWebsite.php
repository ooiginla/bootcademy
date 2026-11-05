<?php

namespace App\Models;

use App\Academies\AcademyWebsiteDefaults;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $academy_id
 * @property string $subdomain
 * @property string $theme_key
 * @property bool $is_published
 * @property array<string, array<string, mixed>> $sections
 * @property-read Academy $academy
 */
#[Fillable(['academy_id', 'subdomain', 'theme_key', 'is_published', 'sections'])]
class AcademyWebsite extends Model
{
    /** @return BelongsTo<Academy, $this> */
    public function academy(): BelongsTo
    {
        return $this->belongsTo(Academy::class);
    }

    /** @return array<string, array<string, mixed>> */
    public function resolvedSections(): array
    {
        return AcademyWebsiteDefaults::merge($this->sections);
    }

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'sections' => 'array',
        ];
    }
}
