<?php

namespace App\Console\Commands;

use App\Models\ChangeLog;
use App\Models\Project;
use App\Models\ProjectTemplate;
use App\Models\ScheduledProject;
use App\Models\User;
use App\Services\ProjectTemplateArchive;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * One-time move of stored template zips into template-type projects.
 *
 * Idempotent via the change log: each conversion writes a 'converted' entry on
 * the new project whose old_value is the old project_templates.id. References
 * (projects.template_id, scheduled_projects.template_id) are repointed in the
 * same transaction as the conversion, never on a re-run: once repointed, a
 * template_id is a project id and could collide with an old template id.
 */
class ConvertTemplatesToProjects extends Command
{
    protected $signature = 'templates:convert-to-projects
        {--only= : Only convert the template with this project_templates id}
        {--purge : After converting, delete the zips of converted templates}
        {--purge-only : With --purge, skip conversion and only purge}';

    protected $description = 'Convert stored project template zips into template projects, repoint their references, and optionally purge converted zips';

    public function handle(ProjectTemplateArchive $archive): int
    {
        $failed = 0;

        if (!$this->option('purge-only')) {
            $templates = ProjectTemplate::query()
                ->when($this->option('only'), fn ($q, $id) => $q->where('id', $id))
                ->orderBy('id')
                ->get();

            foreach ($templates as $template) {
                if ($this->conversionLog($template->id)) {
                    $this->line('Template #' . $template->id . ' "' . $template->name . '" already converted, skipping.');
                    continue;
                }

                try {
                    $project = $this->convert($archive, $template);
                    $this->info('Converted template #' . $template->id . ' "' . $template->name . '" to project #' . $project->id . '.');
                } catch (\Throwable $e) {
                    $failed++;
                    report($e);
                    $this->error('Template #' . $template->id . ' "' . $template->name . '" failed: ' . $e->getMessage());
                }
            }
        }

        if ($this->option('purge') || $this->option('purge-only')) {
            $this->purge();
        }

        return $failed ? 1 : 0;
    }

    private function conversionLog(int $templateId): ?ChangeLog
    {
        return ChangeLog::where('entity_type', 'projects')
            ->where('verb', 'converted')
            ->where('field', 'project_template_id')
            ->where('old_value', (string) $templateId)
            ->first();
    }

    private function convert(ProjectTemplateArchive $archive, ProjectTemplate $template): Project
    {
        $creator = User::find($template->created_by)
            ?? throw new \RuntimeException('creator no longer exists');

        if (!Storage::disk('private')->exists($template->filename)) {
            throw new \RuntimeException('zip ' . $template->filename . ' is missing');
        }

        return DB::transaction(function () use ($archive, $template, $creator) {
            $project = $archive->createProject(
                Storage::disk('private')->path($template->filename),
                $template->name,
                $creator,
            );

            $project->update([
                'project_type' => Project::TYPE_TEMPLATE,
                'is_public'    => $template->is_public,
                'description'  => $template->description,
            ]);

            ChangeLog::create([
                'date'        => now(),
                'user_id'     => $creator->id,
                'entity_type' => 'projects',
                'entity_id'   => $project->id,
                'description' => 'converted from stored template "' . $template->name . '"',
                'verb'        => 'converted',
                'field'       => 'project_template_id',
                'old_value'   => (string) $template->id,
            ]);

            Project::where('template_id', $template->id)
                ->where('id', '!=', $project->id)
                ->update(['template_id' => $project->id]);
            ScheduledProject::where('template_id', $template->id)
                ->update(['template_id' => $project->id]);

            return $project;
        });
    }

    private function purge(): void
    {
        foreach (ProjectTemplate::orderBy('id')->get() as $template) {
            if (!$this->conversionLog($template->id)) {
                continue;
            }
            if ($template->filename && Storage::disk('private')->exists($template->filename)) {
                Storage::disk('private')->delete($template->filename);
                $this->info('Purged zip for template #' . $template->id . '.');
            }
        }
    }
}
