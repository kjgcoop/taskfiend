<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectReminder extends Model
{
    protected $fillable = [
        'project_id',
        'user_id',
        'date',
        'recurrence_pattern',
        'recurrence_floating',
        'dismissed',
    ];

    protected $casts = [
        'date'                => 'date',
        'recurrence_floating' => 'boolean',
        'dismissed'           => 'boolean',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
