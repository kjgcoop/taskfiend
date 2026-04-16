<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Log;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'email_enabled_at',
        'profile_image',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
//            'email_verified_at' => 'datetime',
            'email_enabled_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isEnabled(): bool
    {
        return $this->email_enabled_at === null;
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class, 'user_id');
    }

    public function defaultProject(): Project
    {
        $project = Project::where('user_id', $this->id)->where('is_default', true)->first();

        if (!$project) {
            Log::error('User has no default project', ['user_id' => $this->id]);
            throw new \RuntimeException("User {$this->id} has no default project.");
        }

        return $project;
    }

    public function createdTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'creator_id');
    }

    public function assignedTasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'assignments', 'assignee_id', 'task_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class, 'assignee_id');
    }

    public function apiKeys(): HasMany
    {
        return $this->hasMany(ApiKey::class);
    }

    public function changeLogs(): HasMany
    {
        return $this->hasMany(ChangeLog::class);
    }

    public function taskAttachments(): HasMany
    {
        return $this->hasMany(TaskAttachment::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }
}
