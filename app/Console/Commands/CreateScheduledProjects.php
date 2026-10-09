<?php

namespace App\Console\Commands;

use App\Models\ActivityNotification;
use App\Models\ChangeLog;
use App\Models\Project;
use App\Models\ScheduledProject;
use Illuminate\Console\Command;

class CreateScheduledProjects extends Command
{
    protected $signature = 'projects:create-scheduled';
    protected $description = 'Create projects that are scheduled to start today';

    public function handle(): void
    {
        $today = now()->toDateString();

        $due = ScheduledProject::whereDate('start_date', $today)
            ->where('is_created', false)
            ->with(['template', 'user'])
            ->get();

        foreach ($due as $scheduled) {
            $template = $scheduled->template;
            $user     = $scheduled->user;

            if (!$template || !$user) {
                continue;
            }

            try {
                // Reads the template's current state, not a snapshot from scheduling time.
                $project = Project::createFromTemplate($template, $scheduled->project_name, $user);
            } catch (\Throwable $e) {
                report($e);
                $this->warn("Failed to create project for scheduled project #{$scheduled->id}: {$e->getMessage()}");
                continue;
            }

            $scheduled->update(['is_created' => true]);

            // Notify the user
            ActivityNotification::create([
                'user_id'     => $user->id,
                'actor_id'    => $user->id,
                'actor_name'  => $user->name,
                'entity_type' => 'projects',
                'entity_id'   => $project->id,
                'entity_name' => $project->name,
                'description' => 'Scheduled project created from template "' . $template->name . '"',
                'seen'        => false,
            ]);

            $this->info("Created project \"{$project->name}\" for user {$user->email}.");
        }
    }
}
