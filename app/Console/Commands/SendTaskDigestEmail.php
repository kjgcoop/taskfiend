<?php

namespace App\Console\Commands;

use App\Models\EmailSubscription;
use App\Models\User;
use App\Services\Mailgun\MailgunClient;
use App\Services\TaskDigestMailer;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendTaskDigestEmail extends Command
{
    protected $signature = 'email:task-digest {email? : Only send to this one user, by email} {--date= : Date to summarize, Y-m-d (defaults to today)}';

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

        $email = $this->argument('email');

        if ($email) {
            $user = User::where('email', $email)->first();

            if (!$user) {
                $this->error("No user found with email address: {$email}");
                return 1;
            }

            $users = collect([$user]);
        } else {
            $users = User::whereNull('email_enabled_at')
                ->subscribedToEmail(EmailSubscription::DAILY_DIGEST)
                ->get();
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
