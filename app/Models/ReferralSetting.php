<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['academy_id', 'is_enabled', 'qualifying_event', 'reward_type', 'reward_basis_points', 'fixed_reward_minor', 'eligible_payment_scope'])]
class ReferralSetting extends Model
{
    public function academy(): BelongsTo
    {
        return $this->belongsTo(Academy::class);
    }

    protected function casts(): array
    {
        return ['is_enabled' => 'boolean', 'reward_basis_points' => 'integer', 'fixed_reward_minor' => 'integer'];
    }
}
