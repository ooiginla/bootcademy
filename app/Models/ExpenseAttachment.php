<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['academy_id', 'academy_expense_id', 'path', 'original_name', 'mime_type', 'size_bytes', 'uploaded_by_user_id'])]
class ExpenseAttachment extends Model
{
    /** @return BelongsTo<AcademyExpense, $this> */
    public function expense(): BelongsTo
    {
        return $this->belongsTo(AcademyExpense::class, 'academy_expense_id');
    }

    protected function casts(): array
    {
        return ['size_bytes' => 'integer'];
    }
}
