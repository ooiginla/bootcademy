<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['academy_id', 'announcement_id', 'academy_invoice_id', 'enrollment_id', 'type', 'channel', 'recipient_email', 'recipient_name', 'status', 'idempotency_key', 'queued_at', 'sent_at', 'failure_reason'])]
class NotificationDelivery extends Model
{
    protected function casts(): array
    {
        return ['queued_at' => 'datetime', 'sent_at' => 'datetime'];
    }
}
