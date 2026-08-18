<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tag extends Model
{
    protected $fillable = [
        'tag_name',
        'color',
        'archived_at',
    ];

    protected $casts = [
        'archived_at' => 'datetime',
    ];

    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'tag_task')->withPivot('sort_order');
    }

    public function changeLogs(): HasMany
    {
        return $this->hasMany(ChangeLog::class, 'entity_id')
            ->where('entity_type', 'tags');
    }

    /** Tags that haven't been archived — the set offered anywhere a tag can be picked. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    /** Archived tags — hidden everywhere except their own show page and the archived list. */
    public function scopeArchived(Builder $query): Builder
    {
        return $query->whereNotNull('archived_at');
    }

    public function getIsArchivedAttribute(): bool
    {
        return $this->archived_at !== null;
    }
}
