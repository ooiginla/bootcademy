<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['academy_id', 'name', 'is_active'])]
class ExpenseCategory extends Model
{
    public function academy(): BelongsTo
    {
        return $this->belongsTo(Academy::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(AcademyExpense::class);
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
