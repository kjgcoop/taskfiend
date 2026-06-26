<?php

namespace App\Console\Commands;

use App\Models\ActivityNotification;
use App\Models\ChangeLog;
use App\Models\ProjectTemplate;
use App\Models\ScheduledProject;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CreateScheduledProjects extends Command
{
    protected $signature = 'projects:create-scheduled';
    protected $description = 'Create projects that are scheduled to start today';

    public function handle(): void
    {
        $today = now()->toDateString();

        $due = ScheduledProject::where('start_date', $today)
            ->where('is_created', false)
            ->with(['template', 'user'])
            ->get();

        foreach ($due as $scheduled) {
            $template = $scheduled->template;
            $user     = $scheduled->user;

            if (!$template || !$user) {
                continue;
            }

            $zipPath = Storage::disk('private')->path($template->filename);
            if (!file_exists($zipPath)) {
                $this->warn("Template file missing for scheduled project #{$scheduled->id}, skipping.");
                continue;
            }

            $controller = new \App\Http\Controllers\ProjectTemplateController();
            $project    = $this->callCreateFromZip($controller, $zipPath, $scheduled->project_name, $user, $template->id);

            if ($project === false) {
                $this->warn("Failed to create project for scheduled project #{$scheduled->id}.");
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

    private function callCreateFromZip($controller, string $zipPath, string $projectName, User $user, int $templateId)
    {
        // Access the private method via reflection
        $method = new \ReflectionMethod($controller, 'createProjectFromZip');
        $method->setAccessible(true);
        return $method->invoke($controller, $zipPath, $projectName, $user, $templateId);
    }
}
