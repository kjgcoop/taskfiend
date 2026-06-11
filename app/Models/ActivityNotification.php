<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityNotification extends Model
{
    protected $table = 'notifications';

    protected $fillable = [
        'user_id',
        'actor_id',
        'actor_name',
        'change_log_id',
        'entity_type',
        'entity_id',
        'entity_name',
        'description',
        'seen',
    ];

    protected $casts = [
        'seen' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function changeLog(): BelongsTo
    {
        return $this->belongsTo(ChangeLog::class, 'change_log_id');
    }
}
