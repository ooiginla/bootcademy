<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['academy_id', 'title', 'body', 'audience_type', 'branch_id', 'programme_id', 'status', 'publish_at', 'published_at', 'expires_at', 'created_by_user_id'])]
class Announcement extends Model
{
    protected function casts(): array
    {
        return ['publish_at' => 'datetime', 'published_at' => 'datetime', 'expires_at' => 'datetime'];
    }
}
