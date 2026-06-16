<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
        'recurrence_floating' => 'boolean',
        'dismissed'           => 'boolean',
    ];

    protected function date(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Carbon::parse($value)->format(config('app.human_date_format')) : null,
            set: fn ($value) => $value instanceof Carbon ? $value->format('Y-m-d') : (string) $value,
        );
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
