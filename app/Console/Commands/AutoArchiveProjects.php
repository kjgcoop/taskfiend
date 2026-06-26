<?php

namespace App\Console\Commands;

use App\Models\Project;
use Illuminate\Console\Command;

class AutoArchiveProjects extends Command
{
    protected $signature = 'projects:auto-archive';
    protected $description = 'Close projects whose end date has passed (marking them done or archived per their setting)';

    public function handle(): void
    {
        $projects = Project::where('status', 'incomplete')
            ->whereNotNull('end_date')
            ->whereDate('end_date', '<', now()->toDateString())
            ->get();

        foreach ($projects as $project) {
            $action = $project->auto_close_action ?? 'archived';
            $project->status = $action;
            $project->save();

            $project->changeLogs()->create([
                'date'        => now(),
                'user_id'     => $project->user_id,
                'entity_type' => 'projects',
                'entity_id'   => $project->id,
                'description' => "auto-{$action}: end date ({$project->end_date->toDateString()}) has passed",
            ]);

            $this->line("Marked {$action} project #{$project->id}: {$project->name}");
        }

        $this->info("Done. Closed {$projects->count()} project(s).");
    }
}
