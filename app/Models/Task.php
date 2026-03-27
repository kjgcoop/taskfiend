<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

class Task extends Model
{
    protected $fillable = [
        'name',
        'description',
        'status',
        'completed_at',
        'creator_id',
        'date',
        'time',
        'duration_minutes',
        'project_id',
        'parent_id',
        'sort_order',
        'recurrence_pattern',
        'recurrence_floating',
    ];

    protected function casts(): array
    {
        return [
            'recurrence_floating' => 'boolean',
            'completed_at'        => 'datetime',
        ];
    }

    protected $attributes = [
        'status' => 'incomplete',
    ];

    protected $appends = ['url'];

    public function getUrlAttribute(): string
    {
        return route('tasks.show', $this->id);
    }

    public function setDescriptionAttribute(?string $value): void
    {
        $this->attributes['description'] = $value ?? '';
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function completionLog(): HasOne
    {
        return $this->hasOne(ChangeLog::class, 'entity_id')
                    ->where('entity_type', 'tasks')
                    ->where('verb', 'completed')
                    ->latestOfMany('date');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_id')
                    ->orderByRaw('CASE WHEN sort_order IS NULL THEN 1 ELSE 0 END, sort_order, date, created_at');
    }

    public function incompleteChildren(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_id')
                    ->where('status', 'incomplete')
                    ->orderByRaw('CASE WHEN sort_order IS NULL THEN 1 ELSE 0 END, sort_order, date, created_at');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'tag_task');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'assignments', 'task_id', 'assignee_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TaskAttachment::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function changeLogs(): HasMany
    {
        return $this->hasMany(ChangeLog::class, 'entity_id')
            ->where('entity_type', 'tasks')
            ->orderBy('date', 'desc');
    }

    public function rescheduleCount(): int
    {
        return $this->changeLogs()->where('verb', 'rescheduled')->count();
    }

    /**
     * Get all descendant tasks (recursive, all levels)
     * Returns flat collection for bulk operations
     */
    public function getAllDescendants(): Collection
    {
        $descendants = collect();

        foreach ($this->children as $child) {
            $descendants->push($child);
            $descendants = $descendants->merge($child->getAllDescendants());
        }

        return $descendants;
    }

    /**
     * Get all ancestor tasks up to root
     * Returns array [immediate parent, grandparent, ..., root]
     */
    public function getAllAncestors(): Collection
    {
        $ancestors = collect();
        $current = $this->parent;

        while ($current) {
            $ancestors->push($current);
            $current = $current->parent;
        }

        return $ancestors;
    }

    /**
     * Get root task (top-level parent)
     */
    public function getRoot(): Task
    {
        $task = $this;
        while ($task->parent_id) {
            $task = $task->parent;
        }
        return $task;
    }

    /**
     * Get depth in hierarchy (0 = root, 1 = child, etc.)
     */
    public function getDepth(): int
    {
        $depth = 0;
        $task = $this;

        while ($task->parent_id) {
            $depth++;
            $task = $task->parent;
        }

        return $depth;
    }

    /**
     * Check if task has any incomplete descendants
     */
    public function hasIncompleteDescendants(): bool
    {
        return $this->getAllDescendants()
                    ->contains(fn($task) => $task->status === 'incomplete');
    }

    /**
     * Check if this task is an ancestor of given task (prevent circular refs)
     */
    public function isAncestorOf(Task $task): bool
    {
        return $task->getAllAncestors()->contains('id', $this->id);
    }

    /**
     * Scope to get only root-level tasks (no parent)
     */
    public function scopeRootLevel($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope to get tasks with their complete subtask hierarchy
     */
    public function scopeWithSubtaskHierarchy($query)
    {
        return $query->with(['children' => function($q) {
            $q->with(['children' => function($q2) {
                $q2->with('children'); // Load 3 levels deep by default
            }]);
        }]);
    }

    /**
     * Format a duration in minutes as a human-readable string (e.g. "2h 20m").
     * Returns null if $minutes is null or zero.
     */
    public static function formatDuration(?int $minutes): ?string
    {
        if (!$minutes) {
            return null;
        }

        $hours = intdiv($minutes, 60);
        $mins  = $minutes % 60;

        if ($hours === 0) {
            return "{$mins}m";
        }

        if ($mins === 0) {
            return "{$hours}h";
        }

        return "{$hours}h {$mins}m";
    }
}
