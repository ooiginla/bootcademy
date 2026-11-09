<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'academy_id', 'enrollment_id', 'academy_cycle_term_id', 'cohort_id', 'registered_at', 'created_by_user_id',
])]
class EnrollmentTermRegistration extends Model
{
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(AcademyCycleTerm::class, 'academy_cycle_term_id');
    }

    public function cohort(): BelongsTo
    {
        return $this->belongsTo(Cohort::class);
    }

    protected function casts(): array
    {
        return ['registered_at' => 'immutable_datetime'];
    }
}
