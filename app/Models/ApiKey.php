<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiKey extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'key_hash',
        'key_prefix',
        'user_id',
        'invalidated_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'invalidated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isValid(): bool
    {
        return $this->invalidated_at === null;
    }
}
