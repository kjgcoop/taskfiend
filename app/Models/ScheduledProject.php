<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledProject extends Model
{
    protected $fillable = [
        'template_id',
        'user_id',
        'project_name',
        'start_date',
        'is_created',
    ];

    protected $casts = [
        'start_date' => 'date',
        'is_created' => 'boolean',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(ProjectTemplate::class, 'template_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
