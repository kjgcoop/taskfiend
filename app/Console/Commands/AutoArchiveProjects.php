<?php

namespace App\Console\Commands;

use App\Models\Project;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

class AutoArchiveProjects extends Command
{
    protected $signature = 'projects:auto-archive';
    protected $description = 'Archive projects whose end date has passed';

    public function handle(): void
    {
        $projects = Project::where('status', 'incomplete')
            ->whereNotNull('end_date')
            ->whereDate('end_date', '<', now()->toDateString())
            ->get();

        foreach ($projects as $project) {
            $project->status = 'archived';
            $project->save();

            $project->changeLogs()->create([
                'date'        => now(),
                'user_id'     => $project->user_id,
                'entity_type' => 'projects',
                'entity_id'   => $project->id,
                'description' => "auto-archived: end date ({$project->end_date->toDateString()}) has passed",
            ]);

            $this->line("Archived project #{$project->id}: {$project->name}");
        }

        $this->info("Done. Archived {$projects->count()} project(s).");
    }
}
