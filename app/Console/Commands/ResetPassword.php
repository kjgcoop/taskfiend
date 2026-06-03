<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class ResetPassword extends Command
{
    protected $signature = 'user:password {email} {password}';
    protected $description = 'Reset a user\'s password';

    public function handle()
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (!$user) {
            $this->error('No user found with that email address.');
            return 1;
        }

        $user->password = Hash::make($this->argument('password'));
        $user->save();

        $this->info("Password updated for {$user->email}.");
        return 0;
    }
}
