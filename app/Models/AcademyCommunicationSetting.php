<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['academy_id', 'automatic_payment_reminders', 'payment_reminder_days', 'automatic_renewal_reminders', 'renewal_reminder_days'])]
class AcademyCommunicationSetting extends Model
{
    /** @return BelongsTo<Academy, $this> */
    public function academy(): BelongsTo
    {
        return $this->belongsTo(Academy::class);
    }

    protected function casts(): array
    {
        return ['automatic_payment_reminders' => 'boolean', 'payment_reminder_days' => 'array', 'automatic_renewal_reminders' => 'boolean', 'renewal_reminder_days' => 'array'];
    }
}
