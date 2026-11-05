<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['academy_id', 'name', 'email', 'phone', 'address'])]
class ExpenseVendor extends Model
{
    public function academy(): BelongsTo
    {
        return $this->belongsTo(Academy::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(AcademyExpense::class);
    }
}
