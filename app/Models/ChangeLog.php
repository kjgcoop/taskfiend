<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
            // Only fan out for tasks and projects (not tags, which aren't "shared")
            if (!in_array($log->entity_type, ['tasks', 'projects'])) {
                return;
            }

            $actor = User::find($log->user_id);
            if (!$actor) {
                return;
            }

            if ($log->entity_type === 'tasks') {
                $entity = Task::find($log->entity_id);
                if (!$entity) return;
                $recipientIds = $entity->assignees()->pluck('users.id')
                    ->push($entity->creator_id)
                    ->unique();
            } else {
                $entity = Project::find($log->entity_id);
                if (!$entity) return;
                $recipientIds = $entity->assignees()->pluck('users.id')
                    ->push($entity->user_id)
                    ->unique();
            }

            $entityName = $log->entity_type === 'tasks'
                ? $entity->name
                : $entity->name;

            $rows = [];
            $now  = now();

            foreach ($recipientIds as $uid) {
                if ($uid == $log->user_id) {
                    continue; // don't notify the person who made the change
                }
                $rows[] = [
                    'user_id'       => $uid,
                    'actor_id'      => $log->user_id,
                    'actor_name'    => $actor->name,
                    'change_log_id' => $log->id,
                    'entity_type'   => $log->entity_type,
                    'entity_id'     => $log->entity_id,
                    'entity_name'   => $entityName,
                    'description'   => $log->description,
                    'seen'          => false,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ];
            }

            if (!empty($rows)) {
                ActivityNotification::insert($rows);
            }
        });
    }
}
