<?php

namespace App\Models;

use Database\Factories\GuardianFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property int $academy_id
 * @property string $first_name
 * @property string $last_name
 * @property string|null $email
 * @property string $phone
 * @property string|null $whatsapp_number
 * @property string|null $signature_path
 * @property-read Collection<int, Player> $players
 */
#[Fillable(['academy_id', 'first_name', 'last_name', 'email', 'phone', 'whatsapp_number', 'signature_path'])]
class Guardian extends Model
{
    /** @use HasFactory<GuardianFactory> */
    use HasFactory;

    /** @return BelongsTo<Academy, $this> */
    public function academy(): BelongsTo
    {
        return $this->belongsTo(Academy::class);
    }

    /** @return BelongsToMany<Player, $this> */
    public function players(): BelongsToMany
    {
        return $this->belongsToMany(Player::class, 'player_guardian')
            ->withPivot(['academy_id', 'relationship', 'is_primary', 'is_emergency_contact', 'is_authorized_pickup'])
            ->withTimestamps();
    }
}
