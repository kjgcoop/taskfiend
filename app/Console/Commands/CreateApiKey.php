<?php

namespace App\Console\Commands;

use App\Models\ApiKey;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateApiKey extends Command
{
    protected $signature = 'apikey:create {email}';

    protected $description = 'Create an API key for a user';

    public function handle()
    {
        $email = $this->argument('email');

        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("No user found with email address: {$email}");
            return 1;
        }

        $plainKey = 'tfk_' . Str::random(40);

        ApiKey::create([
            'key_hash' => Hash::make($plainKey),
            'key_prefix' => substr($plainKey, 0, 12),
            'user_id' => $user->id,
            'invalidated_at' => null,
        ]);

        $this->info("API key created successfully for {$user->email} ({$user->name})");
        $this->info("");
        $this->warn("IMPORTANT: Store this key securely. It will not be shown again.");
        $this->info("");
        $this->line("API Key: {$plainKey}");
        $this->info("");

        return 0;
    }
}
