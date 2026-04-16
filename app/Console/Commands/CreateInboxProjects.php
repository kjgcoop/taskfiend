<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\User;
use Illuminate\Console\Command;

class CreateInboxProjects extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inbox:create';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create Inbox projects for all users who don\'t have one';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $users = User::all();
        $created = 0;

        foreach ($users as $user) {
            // Check if user already has an Inbox project
            $hasInbox = Project::where('user_id', $user->id)
                ->where('is_default', true)
                ->exists();

            if (!$hasInbox) {
                Project::create([
                    'name' => $user->name . "'s Inbox",
                    'description' => 'Personal inbox for quick task capture',
                    'user_id' => $user->id,
                    'status' => 'incomplete',
                    'is_default' => true,
                ]);
                $created++;
                $this->info("Created Inbox for {$user->name} ({$user->email})");
            }
        }

        $this->info("Created {$created} Inbox project(s).");
        return 0;
    }
}
