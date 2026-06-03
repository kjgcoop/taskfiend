<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    protected $fillable = [
        'name',
        'description',
        'background_image',
        'user_id',
        'status',
        'end_date',
        'is_default',
        'is_hearted',
        'template_id',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_hearted' => 'boolean',
        'end_date'   => 'date',
    ];

    protected $attributes = [
        'status' => 'incomplete',
    ];

    protected $appends = ['url'];

    public function getUrlAttribute(): string
    {
        return route('projects.show', $this->id);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function changeLogs(): HasMany
    {
        return $this->hasMany(ChangeLog::class, 'entity_id')
            ->where('entity_type', 'projects');
    }

    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_user')
            ->withTimestamps();
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ProjectTemplate::class, 'template_id');
    }

    /**
     * Scope to incomplete projects the given user can interact with:
     * projects they own, are assigned to at the project level, or have
     * at least one task assigned to them within.
     */
    public function scopeActiveForUser(Builder $query, int $userId): Builder
    {
        return $query->where('status', 'incomplete')
                     ->where(function ($q) use ($userId) {
                         $q->where('user_id', $userId)
                           ->orWhereHas('assignees', fn($q2) => $q2->where('users.id', $userId))
                           ->orWhereHas('tasks.assignees', fn($q2) => $q2->where('users.id', $userId));
                     });
    }
}
