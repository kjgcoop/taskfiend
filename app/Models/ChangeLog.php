<?php

namespace App\Models;

use App\Http\Controllers\NotificationsController;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class ChangeLog extends Model
{
    protected $fillable = [
        'date',
        'user_id',
        'entity_type',
        'entity_id',
        'description',
        'verb',
        'field',
        'old_value',
        'new_value',
    ];

    protected $casts = [
        'date' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function booted(): void
    {
        static::created(function (ChangeLog $log) {
            // Bust the notification cache for all users involved in the changed entity
            // so their badge count updates within the next cache TTL cycle.
            $userIds = collect();

            if ($log->entity_type === 'tasks') {
                $task = Task::find($log->entity_id);
                if ($task) {
                    $userIds = $task->assignees()->pluck('users.id')
                        ->push($task->creator_id)
                        ->unique();
                }
            } elseif ($log->entity_type === 'projects') {
                $project = Project::find($log->entity_id);
                if ($project) {
                    $userIds = $project->assignees()->pluck('users.id')
                        ->push($project->user_id)
                        ->unique();
                }
            }

            foreach ($userIds as $uid) {
                if ($uid !== $log->user_id) {
                    NotificationsController::clearCache($uid);
                }
            }
        });
    }
}
