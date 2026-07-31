<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Task extends Model
{
    protected $fillable = [
        'name',
        'description',
        'location',
        'show_map',
        'status',
        'completed_at',
        'creator_id',
        'date',
        'time',
        'duration_minutes',
        'project_id',
        'parent_id',
        'sort_order',
        'project_sort_order',
        'recurrence_pattern',
        'recurrence_floating',
        'recurrence_end_date',
    ];

    protected function casts(): array
    {
        return [
            'recurrence_floating' => 'boolean',
            'show_map'            => 'boolean',
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
     * Scope: tasks visible to the given user — tasks they created or are
     * assigned to. This is the canonical visibility rule for task lists;
     * use it everywhere instead of hand-rolling the creator/assignee check.
     */
    public function scopeVisibleTo(Builder $query, int $userId): Builder
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('creator_id', $userId)
              ->orWhereHas('assignees', fn ($aq) => $aq->where('users.id', $userId));
        });
    }

    /**
     * Scope: tasks whose description or comments contain a reference to $taskId.
     * Covers [task:N], /tasks/N URLs, and ?task=N / &task=N sidebar URLs.
     */
    public function scopeReferencingTask($query, int $taskId)
    {
        return $query->where(function ($q) use ($taskId) {
            $q->where('description', 'like', '%[task:' . $taskId . ']%')
              ->orWhere('description', 'like', '%/tasks/' . $taskId . '%')
              ->orWhere('description', 'like', '%?task=' . $taskId . '%')
              ->orWhere('description', 'like', '%&task=' . $taskId . '%')
              ->orWhereHas('comments', function ($cq) use ($taskId) {
                  $cq->where('comment', 'like', '%[task:' . $taskId . ']%')
                     ->orWhere('comment', 'like', '%/tasks/' . $taskId . '%')
                     ->orWhere('comment', 'like', '%?task=' . $taskId . '%')
                     ->orWhere('comment', 'like', '%&task=' . $taskId . '%');
              });
        });
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
     * Duplicate this task, optionally recursing into its subtask tree. The
     * single implementation behind the user-initiated "Duplicate" button,
     * project duplication, and recurring-task rollover — each supplies the
     * overrides and flags that make sense for it.
     *
     * Every duplicated attachment gets its own physical file copy (never a
     * second row pointing at the same path), so deleting an attachment from
     * one copy can never remove the file out from under another copy that
     * happens to reference the same upload.
     *
     * @param array         $overrides        Fields to override on this copy (e.g. name, date, project_id).
     * @param bool          $withChildren     Recursively duplicate children too. Off by default — the
     *                                        manual "Duplicate" button only ever copies the single task.
     * @param \Closure|null $childFilter      fn(Task $child): bool — which children to include when
     *                                        $withChildren is set. Defaults to including all children.
     * @param array         $childOverrides   Extra field overrides applied to every duplicated child, in
     *                                        addition to reparenting it under this copy.
     * @param bool          $preserveOwnership When true, the copy keeps the original creator_id and each
     *                                        assignment's original assignee/assigned_by/uploader — used for
     *                                        automatic system copies (recurring rollover) where the task
     *                                        shouldn't change hands just because someone else completed it.
     *                                        When false (default — the user-initiated "Duplicate" action),
     *                                        the copy is attributed to the current user, with assignees
     *                                        falling back to the current user when the source has none.
     * @param \Closure|null $afterChildCreate fn(Task $originalChild, Task $newChild): void — called after
     *                                        each child (not the root) is created, e.g. to write a change log.
     */
    public function duplicate(
        array $overrides = [],
        bool $withChildren = false,
        ?\Closure $childFilter = null,
        array $childOverrides = [],
        bool $preserveOwnership = false,
        ?\Closure $afterChildCreate = null,
    ): self {
        $this->loadMissing(['tags', 'assignees', 'attachments']);

        $new = self::create(array_merge([
            'name'                => $this->name,
            'description'         => $this->description,
            'location'            => $this->location,
            'show_map'            => $this->show_map,
            'date'                => $this->date,
            'time'                => $this->time,
            'duration_minutes'    => $this->duration_minutes,
            'project_id'          => $this->project_id,
            'parent_id'           => $this->parent_id,
            'recurrence_pattern'  => $this->recurrence_pattern,
            'recurrence_floating' => $this->recurrence_floating,
            'recurrence_end_date' => $this->recurrence_end_date,
            'creator_id'          => $preserveOwnership ? $this->creator_id : auth()->id(),
            'status'              => 'incomplete',
        ], $overrides));

        $new->tags()->sync($this->tags->pluck('id'));

        if ($preserveOwnership) {
            foreach ($this->assignments as $assignment) {
                $new->assignments()->create([
                    'assignee_id'    => $assignment->assignee_id,
                    'assigned_by_id' => $assignment->assigned_by_id,
                ]);
            }
        } else {
            $assigneeIds = $this->assignees->pluck('id')->toArray() ?: [auth()->id()];
            foreach ($assigneeIds as $assigneeId) {
                $new->assignments()->create([
                    'assignee_id'    => $assigneeId,
                    'assigned_by_id' => auth()->id(),
                ]);
            }
        }

        foreach ($this->attachments as $attachment) {
            $extension = pathinfo($attachment->file_path, PATHINFO_EXTENSION);
            $newPath = 'task_attachments/' . Str::random(40) . ($extension ? '.' . $extension : '');
            Storage::disk('private')->copy($attachment->file_path, $newPath);
            $new->attachments()->create([
                'user_id'           => $preserveOwnership ? $attachment->user_id : auth()->id(),
                'file_path'         => $newPath,
                'original_filename' => $attachment->original_filename,
                'mime_type'         => $attachment->mime_type,
                'file_size'         => $attachment->file_size,
            ]);
        }

        if ($withChildren) {
            $children = $childFilter ? $this->children->filter($childFilter) : $this->children;
            foreach ($children as $child) {
                $newChild = $child->duplicate(
                    overrides: array_merge($childOverrides, ['parent_id' => $new->id]),
                    withChildren: true,
                    childFilter: $childFilter,
                    childOverrides: $childOverrides,
                    preserveOwnership: $preserveOwnership,
                    afterChildCreate: $afterChildCreate,
                );
                if ($afterChildCreate) {
                    $afterChildCreate($child, $newChild);
                }
            }
        }

        return $new;
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
