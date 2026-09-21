<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailSubscription extends Model
{
    public const DAILY_DIGEST = 'daily_digest';

    /**
     * The email types a user can opt into, keyed by the value stored in
     * `type`. Single source of truth for both the profile preferences form
     * and any command/job that needs to know who to send a given email to.
     */
    public const TYPES = [
        self::DAILY_DIGEST => 'Daily Task Digest',
    ];

    protected $fillable = [
        'user_id',
        'type',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
