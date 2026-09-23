<?php

namespace App\Services;

use App\Models\User;
use App\Services\Mailgun\MailgunClient;
use Carbon\Carbon;
use Illuminate\Support\Facades\View;
use RuntimeException;

/**
 * Emails a user one day's task list rendered as a PNG — the same image the
 * day view's "Export PNG" button produces (DayPngExporter), embedded inline so
 * it shows in the message body and can be saved/printed from there.
 *
 * Same ground rules as TaskDigestMailer: request/session-free, and callers are
 * responsible for only invoking it for users opted into the daily_png email
 * (see `EmailSubscription`). The date is always supplied by the caller — this
 * class has no notion of "today".
 */
class TaskPngMailer
{
    public function __construct(
        private readonly MailgunClient $mailgun,
        private readonly TaskDigestMailer $digest,
    ) {
    }

    /**
     * Build and send the image for one user and date. Returns false (no email
     * sent) when the user has no tasks due that day — nothing to report.
     *
     * @throws RuntimeException for a disabled account. Never emailed, whoever the caller is.
     */
    public function send(User $user, Carbon $date): bool
    {
        if (!$user->isEnabled()) {
            throw new RuntimeException("Not emailing {$user->email}: account is disabled.");
        }

        // Same task set as the digest email, so the two can't disagree about
        // what "your tasks for the day" means.
        $tasks = $this->digest->tasksFor($user, $date);

        if ($tasks->isEmpty()) {
            return false;
        }

        $png = DayPngExporter::build($date, $tasks, null, 'date', false, (int) config('taskfiend.day_export_png_width'));
        $filename = 'taskfiend-day-' . $date->format('Y-m-d') . '.png';

        $html = View::make('emails.daily-task-png', [
            'user' => $user,
            'date' => $date,
            'imageCid' => $filename,
        ])->render();

        $this->mailgun->send([
            'from' => $this->mailgun->defaultFrom(),
            'to' => sprintf('%s <%s>', $user->name, $user->email),
            'subject' => sprintf('Your Task List for %s', $date->format('l, F j, Y')),
            'html' => $html,
            'text' => sprintf(
                "Your task list for %s is attached as an image (%s).\n\n%s",
                $date->format('l, F j, Y'),
                $filename,
                route('day', ['date' => $date->format('Y-m-d')]),
            ),
            'inline' => [['filename' => $filename, 'contents' => $png]],
        ]);

        return true;
    }
}
