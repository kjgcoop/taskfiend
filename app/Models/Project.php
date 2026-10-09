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
        'auto_close_action',
        'is_default',
        'is_hearted',
        'template_id',
        'project_type',
        'is_public',
    ];

    public const TYPE_NORMAL = 'normal';
    public const TYPE_TEMPLATE = 'template';

    protected $casts = [
        'is_public'  => 'boolean',
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

    public function isTemplate(): bool
    {
        return $this->project_type === self::TYPE_TEMPLATE;
    }

    /**
     * Template access rule (the single place it lives): a template is visible
     * to its creator, and to everyone else only when public. Only the creator
     * may write. Always false for normal projects, whose rules are untouched
     * (is_public means nothing there).
     */
    public function templateViewableBy(int $userId): bool
    {
        return $this->isTemplate() && ($this->user_id === $userId || $this->is_public);
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

    /** The template this project was created from (itself a Project row). */
    public function template(): BelongsTo
    {
        return $this->belongsTo(self::class, 'template_id');
    }

    /** Projects created from this template. */
    public function instances(): HasMany
    {
        return $this->hasMany(self::class, 'template_id');
    }

    /** Most recent time this template was used to create a project, or null. */
    public function getLastUsedAtAttribute(): ?string
    {
        return $this->instances()->max('created_at');
    }

    /**
     * Instantiate a template for a user (the one path behind create-from-template,
     * the scheduled command and "create now"), change-logging the result.
     */
    public static function createFromTemplate(self $template, string $name, User $user): self
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($template, $name, $user) {
            $project = $template->duplicate($name, $user->id);

            ChangeLog::create([
                'date'        => now(),
                'user_id'     => $user->id,
                'entity_type' => 'projects',
                'entity_id'   => $project->id,
                'description' => 'created project from template "' . $template->name . '"',
            ]);

            return $project;
        });
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
    public function duplicate(?string $name = null, ?int $actingUserId = null): self
    {
        $actorId = $actingUserId ?? auth()->id();
        $fromTemplate = $this->isTemplate();

        $new = self::create([
            'name'         => $name ?? (config('app.duplicate_name_prefix', true) ? 'Copy of ' . $this->name : $this->name),
            'description'  => $this->description,
            'end_date'     => $this->end_date,
            'user_id'      => $actorId,
            'status'       => 'incomplete',
            'project_type' => self::TYPE_NORMAL,
            'is_public'    => false,
            'template_id'  => $fromTemplate ? $this->id : null,
        ]);

        // Instances of a template belong only to whoever instantiated it.
        $new->assignees()->sync(
            $fromTemplate ? [$actorId] : ($this->assignees->pluck('id')->toArray() ?: [$actorId])
        );

        // Templates are date-less: copies never carry dates, at any depth.
        $dateless = $fromTemplate ? ['date' => null, 'time' => null] : [];

        $this->tasks()
            ->where('status', 'incomplete')
            ->whereNull('parent_id')
            ->get()
            ->each(function (Task $task) use ($new, $actorId, $fromTemplate, $dateless) {
                // Every task (at every depth) moves into the new project, and
                // only incomplete subtasks come along for the ride.
                $task->duplicate(
                    overrides: ['project_id' => $new->id] + $dateless,
                    withChildren: true,
                    childFilter: fn (Task $child) => $child->status === 'incomplete',
                    childOverrides: ['project_id' => $new->id] + $dateless,
                    actingUserId: $actorId,
                    assignToActorOnly: $fromTemplate,
                );
            });

        return $new;
    }

    /**
     * Scope: projects where the given user is a member — the owner or a
     * project-level assignee. This is the access rule for acting on a
     * project (e.g. creating or moving tasks into it), which is stricter
     * than activeForUser (that also grants visibility via assigned tasks).
     */
    public function scopeForMember(Builder $query, int $userId): Builder
    {
        // Templates are never a place to act on (pickers, move targets, lists).
        return $query->where('project_type', '!=', self::TYPE_TEMPLATE)->where(function ($q) use ($userId) {
            $q->where('user_id', $userId)
              ->orWhereHas('assignees', fn ($q2) => $q2->where('users.id', $userId));
        });
    }

    public function scopeActiveForUser(Builder $query, int $userId): Builder
    {
        return $query->where('status', 'incomplete')
                     ->where('project_type', '!=', self::TYPE_TEMPLATE)
                     ->where(function ($q) use ($userId) {
                         $q->where('user_id', $userId)
                           ->orWhereHas('assignees', fn($q2) => $q2->where('users.id', $userId))
                           ->orWhereHas('tasks.assignees', fn($q2) => $q2->where('users.id', $userId));
                     });
    }
}
