<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesEmailRecipients;
use App\Models\EmailSubscription;
use App\Services\Mailgun\MailgunClient;
use App\Services\TaskPngMailer;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendTaskPngEmail extends Command
{
    use ResolvesEmailRecipients;

    protected $signature = 'email:task-png {email? : Only send to this one user, by email} {--all : Send to every user subscribed to the daily PNG email. Required when no email is given.} {--force : With an email address, send even if that user hasn\'t opted in. Never sends to a disabled account.}';

    protected $description = "Email a user today's task list as a PNG image, via Mailgun";

    public function handle(TaskPngMailer $mailer, MailgunClient $mailgun): int
    {
        $configErrors = $mailgun->configurationErrors();

        if (!empty($configErrors)) {
            foreach ($configErrors as $error) {
                $this->error($error);
            }
            return 1;
        }

        // Always today for now; TaskPngMailer takes the date as a parameter so
        // another day would only need changing here.
        $date = Carbon::today();

        $users = $this->resolveRecipients(EmailSubscription::DAILY_PNG);

        if ($users === null) {
            return 1;
        }

        $sent = 0;
        $skipped = 0;

        foreach ($users as $user) {
            try {
                if ($mailer->send($user, $date)) {
                    $this->info("Sent task list image to {$user->email}.");
                    $sent++;
                } else {
                    $this->line("Skipped {$user->email} — no tasks for {$date->format('Y-m-d')}.");
                    $skipped++;
                }
            } catch (\Throwable $e) {
                $this->error("Failed to send task list image to {$user->email}: {$e->getMessage()}");
            }
        }

        $this->info("Done. Sent: {$sent}, skipped: {$skipped}.");

        return 0;
    }
}
