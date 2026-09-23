<?php

namespace App\Console\Commands\Concerns;

use App\Models\EmailSubscription;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Recipient selection shared by the email:* commands, which each take
 * `{email?}`, `--all` and `--force`.
 *
 * - An email address sends to that one user, but only if they've opted into
 *   $type. --force skips the opt-in check (for testing) and says so.
 * - --all sends to every enabled user opted into $type.
 * - A disabled account is never a recipient, --force or not. The mailers
 *   check this too, so it holds for any future caller that isn't a command.
 */
trait ResolvesEmailRecipients
{
    /** The users to send to, or null (after printing why) if the command should stop. */
    protected function resolveRecipients(string $type): ?Collection
    {
        $email = $this->argument('email');

        if ($email) {
            $user = User::where('email', $email)->first();

            if (!$user) {
                $this->error("No user found with email address: {$email}");
                return null;
            }

            if (!$user->isEnabled()) {
                $this->error("{$email}'s account is disabled. Not sending.");
                return null;
            }

            if (!$user->isSubscribedToEmail($type)) {
                if (!$this->option('force')) {
                    $this->error("{$email} hasn't opted in to this email. Use --force to send anyway.");
                    return null;
                }

                $this->warn("{$email} hasn't opted in to this email; sending anyway because of --force.");
            }

            return collect([$user]);
        }

        if ($this->option('force')) {
            $this->error('--force only applies when sending to a single email address.');
            return null;
        }

        if ($this->option('all')) {
            return User::whereNull('email_enabled_at')
                ->subscribedToEmail($type)
                ->get();
        }

        $this->error('Pass an email address to send to one user, or --all to send to every subscribed user.');
        return null;
    }
}
