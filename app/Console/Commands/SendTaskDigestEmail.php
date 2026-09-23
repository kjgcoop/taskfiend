<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesEmailRecipients;
use App\Models\EmailSubscription;
use App\Services\Mailgun\MailgunClient;
use App\Services\TaskDigestMailer;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendTaskDigestEmail extends Command
{
    use ResolvesEmailRecipients;

    protected $signature = 'email:task-digest {email? : Only send to this one user, by email} {--date= : Date to summarize, Y-m-d (defaults to today)} {--all : Send to every user subscribed to the daily digest. Required when no email is given.} {--force : With an email address, send even if that user hasn\'t opted in. Never sends to a disabled account.}';

    protected $description = "Email a user their tasks for the day, via Mailgun";

    public function handle(TaskDigestMailer $mailer, MailgunClient $mailgun): int
    {
        $configErrors = $mailgun->configurationErrors();

        if (!empty($configErrors)) {
            foreach ($configErrors as $error) {
                $this->error($error);
            }
            return 1;
        }

        $date = $this->option('date') ? Carbon::parse($this->option('date')) : Carbon::today();

        $users = $this->resolveRecipients(EmailSubscription::DAILY_DIGEST);

        if ($users === null) {
            return 1;
        }

        $sent = 0;
        $skipped = 0;

        foreach ($users as $user) {
            try {
                if ($mailer->send($user, $date)) {
                    $this->info("Sent digest to {$user->email}.");
                    $sent++;
                } else {
                    $this->line("Skipped {$user->email} — no tasks for {$date->format('Y-m-d')}.");
                    $skipped++;
                }
            } catch (\Throwable $e) {
                $this->error("Failed to send digest to {$user->email}: {$e->getMessage()}");
            }
        }

        $this->info("Done. Sent: {$sent}, skipped: {$skipped}.");

        return 0;
    }
}
