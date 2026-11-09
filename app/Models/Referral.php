<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['academy_id', 'referrer_player_id', 'referred_application_id', 'referred_player_id', 'status', 'source', 'notes', 'attributed_at', 'qualified_at', 'created_by_user_id'])]
class Referral extends Model
{
    public function referrer(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'referrer_player_id');
    }

    public function referredPlayer(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'referred_player_id');
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'referred_application_id');
    }

    public function rewards(): HasMany
    {
        return $this->hasMany(ReferralReward::class);
    }

    protected function casts(): array
    {
        return ['attributed_at' => 'datetime', 'qualified_at' => 'datetime'];
    }
}
