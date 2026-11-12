<?php

namespace App\Models;

use Database\Factories\ProgrammeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'academy_id', 'name', 'code', 'description', 'duration', 'delivery_format',
    'programme_type', 'public_timezone', 'level', 'outcomes_html', 'skills_gained_html',
    'curriculum_html', 'eligibility_html', 'audience_html', 'demand_html',
    'application_process_html', 'tools_platforms', 'faqs', 'testimonials', 'hero_image_path', 'is_active',
])]
class Programme extends Model
{
    /** @use HasFactory<ProgrammeFactory> */
    use HasFactory, SoftDeletes;

    /** @return BelongsTo<Academy, $this> */
    public function academy(): BelongsTo
    {
        return $this->belongsTo(Academy::class);
    }

    /** @return BelongsToMany<Branch, $this> */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'programme_branch')
            ->withPivot('academy_id')
            ->withTimestamps();
    }

    /** @return HasMany<Enrollment, $this> */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tools_platforms' => 'array',
            'faqs' => 'array',
            'testimonials' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
