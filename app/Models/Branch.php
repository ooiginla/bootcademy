<?php

namespace App\Models;

use Database\Factories\BranchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property int $academy_id
 * @property string $name
 */
#[Fillable([
    'academy_id',
    'name',
    'code',
    'address_line',
    'city',
    'state_region',
    'country_code',
    'timezone',
    'contact_email',
    'contact_phone',
    'is_active',
])]
class Branch extends Model
{
    /** @use HasFactory<BranchFactory> */
    use HasFactory;

    /** @return BelongsTo<Academy, $this> */
    public function academy(): BelongsTo
    {
        return $this->belongsTo(Academy::class);
    }

    /** @return BelongsToMany<Programme, $this> */
    public function programmes(): BelongsToMany
    {
        return $this->belongsToMany(Programme::class, 'programme_branch')
            ->withPivot('academy_id')
            ->withTimestamps();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
