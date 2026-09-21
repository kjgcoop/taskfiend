<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use App\Services\Mailgun\MailgunClient;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;

/**
 * Builds and sends a user's "tasks for the day" digest email.
 *
 * Deliberately request/session-free (no Auth::id(), no request()) so it can be
 * called the same way from an artisan command (now) or a queued/scheduled job
 * (later, once users can opt in/out in their profile) without any changes.
 */
class TaskDigestMailer
{
    public function __construct(private readonly MailgunClient $mailgun)
    {
    }

    /**
     * The user's incomplete tasks scheduled for the given date, same visibility
     * and active-project rules as the Day page (DashboardController::day()).
     */
    public function tasksFor(User $user, Carbon $date): Collection
    {
        return Task::query()
            ->visibleTo($user->id)
            ->where('status', 'incomplete')
            ->where('date', $date->format('Y-m-d'))
            ->whereHas('project', fn ($q) => $q->whereNotIn('status', ['archived', 'done']))
            ->with(['project', 'tags'])
            ->orderByRaw('time IS NULL, time ASC')
            ->get();
    }

    /**
     * Build and send the digest for one user. Returns false (no email sent)
     * when the user has no tasks due that day — nothing to report.
     */
    public function send(User $user, ?Carbon $date = null): bool
    {
        $date ??= Carbon::today();
        $tasks = $this->tasksFor($user, $date);

        if ($tasks->isEmpty()) {
            return false;
        }

        $html = View::make('emails.daily-task-digest', [
            'user' => $user,
            'date' => $date,
            'tasks' => $tasks,
        ])->render();

        $this->mailgun->send([
            'from' => $this->mailgun->defaultFrom(),
            'to' => sprintf('%s <%s>', $user->name, $user->email),
            'subject' => sprintf('Your Tasks for %s', $date->format('l, F j, Y')),
            'html' => $html,
            'text' => $this->plainTextVersion($tasks, $date),
        ]);

        return true;
    }

    private function plainTextVersion(Collection $tasks, Carbon $date): string
    {
        $lines = ["Your tasks for {$date->format('l, F j, Y')}:", ''];

        foreach ($tasks as $task) {
            $time = $task->time ? Carbon::parse($task->time)->format('g:i A') . ' — ' : '';
            $project = $task->project ? " ({$task->project->name})" : '';
            $lines[] = "- {$time}{$task->name}{$project}";
        }

        return implode("\n", $lines);
    }
}
