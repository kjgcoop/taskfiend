<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectReminder;
use App\Models\Task;
use App\Services\DateParser;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TaskApiController extends Controller
{
    public function create(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'date' => 'nullable|date_format:Y-m-d',
            'time' => 'nullable|date_format:H:i',
            'project_id' => 'nullable|exists:projects,id',
            'recurrence_pattern' => 'nullable|string',
            'recurrence_floating' => 'nullable|boolean',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'exists:tags,id',
            'assignee_ids' => 'nullable|array',
            'assignee_ids.*' => 'exists:users,id',
        ]);

        $user = $request->user();

        // Reject project IDs the user doesn't have access to (creator or assignee)
        if (!empty($validated['project_id'])) {
            $hasAccess = Project::where('id', $validated['project_id'])
                ->where(function ($q) use ($user) {
                    $q->where('user_id', $user->id)
                      ->orWhereHas('assignees', fn ($q2) => $q2->where('users.id', $user->id));
                })
                ->exists();

            if (!$hasAccess) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this project.',
                ], 422);
            }
        }

        $taskName = $validated['name'];
        $date = $validated['date'] ?? null;
        $time = $validated['time'] ?? null;
        $recurrencePattern = $validated['recurrence_pattern'] ?? null;
        $recurrenceFloating = !empty($validated['recurrence_floating']);

        $dateParser = new DateParser();

        // Validate and normalize explicitly provided recurrence pattern
        if ($recurrencePattern) {
            $normalized = $dateParser->normalizeRecurrencePattern($recurrencePattern);
            if ($normalized === null) {
                return response()->json([
                    'success' => false,
                    'message' => "The recurrence pattern '{$recurrencePattern}' is not recognized. Supported patterns include: daily, every other day, weekdays, weekends, every Monday/Tuesday/etc., every other Monday/Tuesday/etc., every 2 weeks, every 1st (monthly), every first Monday (monthly), yearly."
                ], 422);
            }
            $recurrencePattern = $normalized;
        }

        if (!$recurrencePattern) {
            // Check for unrecognized recurrence patterns first
            $unrecognizedError = $dateParser->detectUnrecognizedPattern($taskName);
            if ($unrecognizedError) {
                return response()->json([
                    'success' => false,
                    'message' => $unrecognizedError,
                ], 422);
            }

            $parsed = $dateParser->parseTaskInput($taskName);
            $taskName = $parsed['name'];
            // Only auto-fill date/time from task name if not explicitly provided
            if (!$date) {
                $date = $parsed['date'];
                $time = $parsed['time'];
            }
            $recurrencePattern = $parsed['recurrence_pattern'];
            if ($parsed['recurrence_floating']) {
                $recurrenceFloating = true;
            }
        }

        // Reject dates in the past (today is always valid)
        if ($date && Carbon::parse($date)->startOfDay()->lt(Carbon::today())) {
            return response()->json([
                'success' => false,
                'message' => 'Task date cannot be in the past.',
            ], 422);
        }

        $projectId = $validated['project_id'] ?? $user->defaultProject()->id;

        $task = Task::create([
            'name' => $taskName,
            'description' => $validated['description'] ?? null,
            'date' => $date,
            'time' => $time,
            'project_id' => $projectId,
            'recurrence_pattern' => $recurrencePattern,
            'recurrence_floating' => $recurrenceFloating,
            'creator_id' => $user->id,
            'status' => 'incomplete',
        ]);

        if (isset($validated['tag_ids'])) {
            $task->tags()->sync($validated['tag_ids']);
        }

        $assigneeIds = $validated['assignee_ids'] ?? [$user->id];
        foreach ($assigneeIds as $assigneeId) {
            $task->assignments()->create([
                'assignee_id' => $assigneeId,
                'assigned_by_id' => $user->id,
            ]);
        }

        $task->changeLogs()->create([
            'date' => now(),
            'user_id' => $user->id,
            'entity_type' => 'tasks',
            'entity_id' => $task->id,
            'description' => 'created task via API',
        ]);

        return response()->json([
            'success' => true,
            'task' => $task->load(['creator', 'project', 'tags', 'assignees']),
        ], 201);
    }

    public function completedOnDay(Request $request, string $date)
    {
        try {
            $carbonDate = Carbon::createFromFormat('Y-m-d', $date);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid date format. Expected YYYY-MM-DD.',
            ], 400);
        }

        $user = $request->user();

        $baseQuery = function ($status) use ($user, $carbonDate) {
            return Task::query()
                ->where(function ($q) use ($user) {
                    $q->where('creator_id', $user->id)
                      ->orWhereHas('assignees', function ($query) use ($user) {
                          $query->where('users.id', $user->id);
                      });
                })
                ->where('status', $status)
                ->whereDate('updated_at', $carbonDate)
                ->with(['creator', 'project', 'tags', 'assignees', 'comments' => function ($q) {
                    $q->orderBy('created_at', 'desc');
                }])
                ->orderBy('date')
                ->get();
        };

        return response()->json([
            'success' => true,
            'date' => $date,
            'done' => $baseQuery('done'),
            'archived' => $baseQuery('archived'),
        ]);
    }

    public function onDay(Request $request, string $date)
    {
        try {
            $carbonDate = Carbon::createFromFormat('Y-m-d', $date);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid date format. Expected YYYY-MM-DD.',
            ], 400);
        }

        $user = $request->user();

        $tasks = Task::query()
            ->where(function ($q) use ($user) {
                $q->where('creator_id', $user->id)
                  ->orWhereHas('assignees', function ($query) use ($user) {
                      $query->where('users.id', $user->id);
                  });
            })
            ->where('status', '!=', 'archived')
            ->where('date', $carbonDate->format('Y-m-d'))
            ->with(['creator', 'project', 'tags', 'assignees', 'comments' => function ($q) {
                $q->orderBy('created_at', 'asc');
            }])
            ->orderBy('date')
            ->get();

        $projectReminders = ProjectReminder::select('project_reminders.*')
            ->join('projects as p', 'p.id', '=', 'project_reminders.project_id')
            ->where('p.user_id', $user->id)
            ->where('p.status', 'incomplete')
            ->where('dismissed', false)
            ->whereDate('date', '<=', $carbonDate->format('Y-m-d'))
            ->with('project')
            ->orderBy('date')
            ->get();

        return response()->json([
            'success' => true,
            'date' => $date,
            'tasks' => $tasks,
            'project_reminders' => $projectReminders,
        ]);
    }
}
