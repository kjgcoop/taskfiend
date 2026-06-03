<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class ResetPassword extends Command
{
    protected $signature = 'user:password {email} {password?}';
    protected $description = 'Reset a user\'s password (generates one if not provided)';

    public function handle()
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (!$user) {
            $this->error('No user found with that email address.');
            return 1;
        }

        $password = $this->argument('password') ?? \Illuminate\Support\Str::random(15);

        $user->password = Hash::make($password);
        $user->save();

        $this->info("Password updated for {$user->email}: {$password}");
        return 0;
    }
}
