<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'sub',
    'sid',
    'laravel_session_id',
    'id_token',
])]
class OidcSession extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
