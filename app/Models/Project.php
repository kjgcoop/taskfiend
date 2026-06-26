<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

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

    public function statusLogs(): HasMany
    {
        return $this->hasMany(ProjectStatusLog::class)->latest();
    }

    public function latestStatusLog(): HasMany
    {
        return $this->hasMany(ProjectStatusLog::class)->latest()->limit(1);
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(ProjectReminder::class);
    }

    /**
     * Scope to incomplete projects the given user can interact with:
     * projects they own, are assigned to at the project level, or have
     * at least one task assigned to them within.
     */
    public function duplicate(): self
    {
        $new = self::create([
            'name'        => 'Copy of ' . $this->name,
            'description' => $this->description,
            'end_date'    => $this->end_date,
            'user_id'     => auth()->id(),
            'status'      => 'incomplete',
        ]);

        $new->assignees()->sync($this->assignees->pluck('id')->toArray() ?: [auth()->id()]);

        $this->tasks()
            ->where('status', 'incomplete')
            ->whereNull('parent_id')
            ->with(['tags', 'assignees', 'attachments', 'children.tags', 'children.assignees', 'children.attachments'])
            ->get()
            ->each(function (Task $task) use ($new) {
                $copy = $task->duplicate(['project_id' => $new->id]);
                $this->duplicateChildren($task, $copy->id, $new->id);
            });

        return $new;
    }

    private function duplicateChildren(Task $parent, int $newParentId, int $newProjectId): void
    {
        foreach ($parent->children as $child) {
            if ($child->status !== 'incomplete') {
                continue;
            }
            $child->loadMissing(['tags', 'assignees', 'attachments', 'children.tags', 'children.assignees', 'children.attachments']);
            $copy = $child->duplicate(['project_id' => $newProjectId, 'parent_id' => $newParentId]);
            $this->duplicateChildren($child, $copy->id, $newProjectId);
        }
    }

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
