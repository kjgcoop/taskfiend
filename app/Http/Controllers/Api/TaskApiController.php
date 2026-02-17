<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'exists:tags,id',
            'assignee_ids' => 'nullable|array',
            'assignee_ids.*' => 'exists:users,id',
        ]);

        $user = $request->user();

        $taskName = $validated['name'];
        $date = $validated['date'] ?? null;
        $time = $validated['time'] ?? null;
        $recurrencePattern = $validated['recurrence_pattern'] ?? null;

        $dateParser = new DateParser();

        // Validate explicitly provided recurrence pattern
        if ($recurrencePattern && !$dateParser->isValidRecurrencePattern($recurrencePattern)) {
            return response()->json([
                'success' => false,
                'message' => "The recurrence pattern '{$recurrencePattern}' is not recognized. Supported patterns include: daily, every other day, weekdays, weekends, every Monday/Tuesday/etc., every other Monday/Tuesday/etc., every 2 weeks, every 1st (monthly), every first Monday (monthly), yearly."
            ], 422);
        }

        if (!$date && !$recurrencePattern) {
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
            $date = $parsed['date'];
            $time = $parsed['time'];
            $recurrencePattern = $parsed['recurrence_pattern'];
        }

        $task = Task::create([
            'name' => $taskName,
            'description' => $validated['description'] ?? null,
            'date' => $date,
            'time' => $time,
            'project_id' => $validated['project_id'] ?? null,
            'recurrence_pattern' => $recurrencePattern,
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

        $tasks = Task::query()
            ->where(function ($q) use ($user) {
                $q->where('creator_id', $user->id)
                  ->orWhereHas('assignees', function ($query) use ($user) {
                      $query->where('users.id', $user->id);
                  });
            })
            ->where('status', 'done')
            ->whereDate('updated_at', $carbonDate)
            ->with(['creator', 'project', 'tags', 'assignees'])
            ->orderBy('date')
            ->get();

        return response()->json([
            'success' => true,
            'date' => $date,
            'tasks' => $tasks,
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
            ->with(['creator', 'project', 'tags', 'assignees'])
            ->orderBy('date')
            ->get();

        return response()->json([
            'success' => true,
            'date' => $date,
            'tasks' => $tasks,
        ]);
    }
}
