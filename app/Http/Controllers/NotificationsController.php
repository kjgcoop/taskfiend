<?php

namespace App\Http\Controllers;

use App\Models\ChangeLog;
use App\Models\Project;
use App\Models\Task;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class NotificationsController extends Controller
{
    /**
     * Build a query for change_log entries made by OTHER users on tasks/projects
     * that the current user is involved in (as creator or assignee).
     */
    private function feedQuery()
    {
        $userId = Auth::id();

        // Tasks the user is involved in
        $taskIds = Task::where('creator_id', $userId)
            ->orWhereHas('assignees', fn($q) => $q->where('users.id', $userId))
            ->pluck('id');

        // Projects the user is involved in
        $projectIds = Project::where('user_id', $userId)
            ->orWhereHas('assignees', fn($q) => $q->where('users.id', $userId))
            ->pluck('id');

        return ChangeLog::where('user_id', '!=', $userId)
            ->where(function ($q) use ($taskIds, $projectIds) {
                $q->where(function ($sq) use ($taskIds) {
                    $sq->where('entity_type', 'tasks')
                       ->whereIn('entity_id', $taskIds);
                })->orWhere(function ($sq) use ($projectIds) {
                    $sq->where('entity_type', 'projects')
                       ->whereIn('entity_id', $projectIds);
                });
            })
            ->with('user')
            ->orderByDesc('date');
    }

    /**
     * Returns the unread notification count for the authenticated user.
     * Used by the nav badge via a shared view composer.
     */
    public static function unreadCount(): int
    {
        $user = Auth::user();
        if (!$user) {
            return 0;
        }

        $userId = $user->id;

        $taskIds = Task::where('creator_id', $userId)
            ->orWhereHas('assignees', fn($q) => $q->where('users.id', $userId))
            ->pluck('id');

        $projectIds = Project::where('user_id', $userId)
            ->orWhereHas('assignees', fn($q) => $q->where('users.id', $userId))
            ->pluck('id');

        $query = ChangeLog::where('user_id', '!=', $userId)
            ->where(function ($q) use ($taskIds, $projectIds) {
                $q->where(function ($sq) use ($taskIds) {
                    $sq->where('entity_type', 'tasks')
                       ->whereIn('entity_id', $taskIds);
                })->orWhere(function ($sq) use ($projectIds) {
                    $sq->where('entity_type', 'projects')
                       ->whereIn('entity_id', $projectIds);
                });
            });

        if ($user->notifications_read_at) {
            $query->where('date', '>', $user->notifications_read_at);
        }

        return $query->count();
    }

    /**
     * Returns the 20 most recent feed entries as JSON, and marks them as read.
     */
    public function feed(Request $request)
    {
        $logs = $this->feedQuery()->take(20)->get();

        $this->attachEntities($logs);

        // Mark as read
        Auth::user()->update(['notifications_read_at' => now()]);

        $html = view('notifications.feed', compact('logs'))->render();

        return response()->json(['html' => $html]);
    }

    private function attachEntities($changeLogs): void
    {
        $taskIds    = $changeLogs->where('entity_type', 'tasks')->pluck('entity_id')->unique();
        $projectIds = $changeLogs->where('entity_type', 'projects')->pluck('entity_id')->unique();

        $tasks    = $taskIds->isNotEmpty()    ? Task::whereIn('id', $taskIds)->get()->keyBy('id')       : collect();
        $projects = $projectIds->isNotEmpty() ? Project::whereIn('id', $projectIds)->get()->keyBy('id') : collect();

        foreach ($changeLogs as $log) {
            $log->entity = match ($log->entity_type) {
                'tasks'    => $tasks->get($log->entity_id),
                'projects' => $projects->get($log->entity_id),
                default    => null,
            };
        }
    }
}
