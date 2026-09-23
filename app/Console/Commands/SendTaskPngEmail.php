<?php

namespace App\Console\Commands;

use App\Models\EmailSubscription;
use App\Models\User;
use App\Services\Mailgun\MailgunClient;
use App\Services\TaskPngMailer;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendTaskPngEmail extends Command
{
    protected $signature = 'email:task-png {email? : Only send to this one user, by email} {--all : Send to every user subscribed to the daily PNG email. Required when no email is given.}';

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

        $email = $this->argument('email');

        if ($email) {
            $user = User::where('email', $email)->first();

            if (!$user) {
                $this->error("No user found with email address: {$email}");
                return 1;
            }

            $users = collect([$user]);
        } elseif ($this->option('all')) {
            $users = User::whereNull('email_enabled_at')
                ->subscribedToEmail(EmailSubscription::DAILY_PNG)
                ->get();
        } else {
            $this->error('Pass an email address to send to one user, or --all to send to every subscribed user.');
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
