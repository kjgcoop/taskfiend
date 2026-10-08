<?php

namespace App\Services;

use App\Exceptions\InvalidTemplateException;
use App\Models\Assignment;
use App\Models\ChangeLog;
use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * The one place that reads and writes project template zips.
 *
 * Zip layout: template.json (the manifest), README.md (for people; never
 * read back), attachments/ (task attachment files). Used by both the stored
 * ProjectTemplate flow (ProjectTemplateController, scheduled projects) and
 * the file-only download/upload flow (DataExportController).
 */
class ProjectTemplateArchive
{
    /**
     * Build a template zip from a project's incomplete tasks, in
     * storage/app/temp. Returns the zip's path, or false if zipping failed.
     * The caller owns the file (stream it with deleteFileAfterSend, or move it).
     */
    public function build(Project $project): string|false
    {
        $data = [
            'exported_at'      => now()->toIso8601String(),
            'template_type'    => 'project',
            'project'          => [
                'name'        => $project->name,
                'description' => $project->description,
            ],
            'tasks'            => [],
            'tags'             => [],
            'task_attachments' => [],
        ];

        $tasks = Task::where('project_id', $project->id)
            ->where('status', 'incomplete')
            ->with(['tags', 'attachments'])
            ->get();

        $taskIdToIndex = $tasks->values()->mapWithKeys(fn ($task, $index) => [$task->id => $index])->all();

        $tempDir = $this->makeTempDir('template_build_' . $project->id);
        $attachmentsDir = $tempDir . '/attachments';
        mkdir($attachmentsDir, 0755, true);

        $tags = [];
        foreach ($tasks as $task) {
            $data['tasks'][] = [
                'name'               => $task->name,
                'description'        => $task->description,
                'date'               => null,
                'time'               => null,
                'location'           => $task->location,
                'recurrence_pattern' => $task->recurrence_pattern,
                'parent_index'       => $taskIdToIndex[$task->parent_id] ?? null,
                'tags'               => $task->tags->pluck('id')->all(),
                'assignees'          => [],
            ];

            foreach ($task->tags as $tag) {
                $tags[$tag->id] = ['id' => $tag->id, 'name' => $tag->tag_name, 'color' => $tag->color];
            }

            foreach ($task->attachments as $attachment) {
                $stored = $this->copyIntoDir($attachment->file_path, $attachmentsDir);
                if ($stored === null) {
                    continue;
                }
                $data['task_attachments'][] = [
                    'task_index' => count($data['tasks']) - 1,
                    'filename'   => $attachment->original_filename,
                    // Name as stored in attachments/ (may carry a _N suffix if two
                    // attachments shared a filename); import resolves it via basename().
                    'path'       => $stored,
                ];
            }
        }
        $data['tags'] = array_values($tags);

        file_put_contents($tempDir . '/template.json', json_encode($data, JSON_PRETTY_PRINT));
        file_put_contents($tempDir . '/README.md', TemplateReadme::build($data));

        // System zip binary, so the php-zip extension isn't needed for writing.
        $zipPath = storage_path('app/temp/template_' . $project->id . '_' . uniqid() . '.zip');
        exec('cd ' . escapeshellarg($tempDir) . ' && zip -r ' . escapeshellarg($zipPath) . ' .', $_, $returnCode);

        $this->deleteDirectory($tempDir);

        return $returnCode === 0 ? $zipPath : false;
    }

    /**
     * Validate a template zip and return its manifest without importing it.
     *
     * @throws InvalidTemplateException
     */
    public function readManifest(string $zipPath): array
    {
        $tempDir = $this->extract($zipPath, 'template_check');
        try {
            return $this->manifestFrom($tempDir);
        } finally {
            $this->deleteDirectory($tempDir);
        }
    }

    /**
     * Create a project (and its whole task tree) from a template zip, owned
     * by $user. All-or-nothing: on any failure the DB is rolled back and any
     * files already copied to the private disk are deleted.
     *
     * @param  int|null  $templateId  the ProjectTemplate this came from, if any
     * @throws InvalidTemplateException  the zip isn't a usable template (message is user-facing)
     * @throws \Throwable                anything else; already rolled back and cleaned up
     */
    public function createProject(string $zipPath, string $projectName, User $user, ?int $templateId = null, ?string $templateName = null): Project
    {
        $tempDir = $this->extract($zipPath, 'template_load_' . $user->id);
        $writtenFiles = [];

        DB::beginTransaction();
        try {
            $data = $this->manifestFrom($tempDir);

            $project = Project::create([
                'name'        => $projectName,
                'description' => $data['project']['description'] ?? '',
                'user_id'     => $user->id,
                'template_id' => $templateId,
            ]);

            $this->log($user, 'projects', $project->id, $templateName !== null
                ? 'created project from template "' . $templateName . '"'
                : 'created project from a template file');

            // Background image (present in full data exports, not in template builds)
            if (!empty($data['project']['background_image'])) {
                $bgFilename = basename($data['project']['background_image']);
                $sourceFile = $tempDir . '/project-backgrounds/' . $bgFilename;
                if (is_file($sourceFile)) {
                    $newBgPath = 'project-backgrounds/' . $project->id . '/' . $bgFilename;
                    Storage::disk('private')->put($newBgPath, file_get_contents($sourceFile));
                    $writtenFiles[] = $newBgPath;
                    $project->update(['background_image' => $newBgPath]);
                }
            }

            $tagIdMap = $this->resolveTags($data['tags'] ?? []);

            $indexToTaskId = [];
            foreach ($data['tasks'] ?? [] as $index => $taskData) {
                $task = Task::create([
                    'name'               => $taskData['name'],
                    'description'        => $taskData['description'] ?? null,
                    'status'             => 'incomplete',
                    'date'               => $taskData['date'] ?? null,
                    'time'               => $taskData['time'] ?? null,
                    'location'           => $taskData['location'] ?? null,
                    'recurrence_pattern' => $taskData['recurrence_pattern'] ?? null,
                    'project_id'         => $project->id,
                    'creator_id'         => $user->id,
                ]);
                $indexToTaskId[$index] = $task->id;

                $this->log($user, 'tasks', $task->id, 'created task via template import');

                $newTagIds = array_values(array_unique(array_filter(array_map(
                    fn ($oldId) => $tagIdMap[$oldId] ?? null,
                    $taskData['tags'] ?? []
                ))));
                if ($newTagIds) {
                    $task->tags()->attach($newTagIds);
                }

                // Assignee ids in a manifest belong to the instance that built it and
                // would point at the wrong people here, so they are ignored: every
                // task is assigned to the importer alone.
                Assignment::create([
                    'task_id'        => $task->id,
                    'assignee_id'    => $user->id,
                    'assigned_by_id' => $user->id,
                ]);

                foreach ($data['task_attachments'] ?? [] as $attachmentData) {
                    if (($attachmentData['task_index'] ?? null) !== $index) {
                        continue;
                    }
                    // basename() on both JSON-supplied names guards against path traversal
                    $sourceFile = $tempDir . '/attachments/' . basename($attachmentData['path']);
                    if (!is_file($sourceFile)) {
                        continue;
                    }
                    $safeFilename = basename($attachmentData['filename']);
                    $newPath = 'task_attachments/' . uniqid() . '_' . $safeFilename;
                    Storage::disk('private')->put($newPath, file_get_contents($sourceFile));
                    $writtenFiles[] = $newPath;
                    TaskAttachment::create([
                        'task_id'           => $task->id,
                        'user_id'           => $user->id,
                        'original_filename' => $safeFilename,
                        'file_path'         => $newPath,
                        'file_size'         => filesize($sourceFile),
                    ]);
                }
            }

            // Second pass: parent/child links, now that every task has an id
            foreach ($data['tasks'] ?? [] as $index => $taskData) {
                $parentIndex = $taskData['parent_index'] ?? null;
                if ($parentIndex !== null && isset($indexToTaskId[$parentIndex], $indexToTaskId[$index])) {
                    Task::where('id', $indexToTaskId[$index])->update(['parent_id' => $indexToTaskId[$parentIndex]]);
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Storage::disk('private')->delete($writtenFiles);
            throw $e;
        } finally {
            $this->deleteDirectory($tempDir);
        }

        return $project;
    }

    /**
     * Map the manifest's tag ids to tags on this instance. Tags are global and
     * names are unique, so match by name (case-insensitively), never by id:
     * ids from another instance, or from before a tag was deleted and
     * recreated, point at the wrong tag or at nothing.
     *
     * @return array<int|string, int>  manifest tag id => local tag id
     */
    private function resolveTags(array $tags): array
    {
        $map = [];
        foreach ($tags as $tagData) {
            $name = trim((string) ($tagData['name'] ?? ''));
            if ($name === '' || !isset($tagData['id'])) {
                continue;
            }
            $tag = Tag::whereRaw('LOWER(tag_name) = ?', [mb_strtolower($name)])->first()
                ?? Tag::create(['tag_name' => $name, 'color' => $tagData['color'] ?? '#6b7280']);
            $map[$tagData['id']] = $tag->id;
        }

        return $map;
    }

    /** @throws InvalidTemplateException */
    private function extract(string $zipPath, string $prefix): string
    {
        $tempDir = $this->makeTempDir($prefix);
        if (!SafeZipExtractor::extract($zipPath, $tempDir)) {
            $this->deleteDirectory($tempDir);
            throw new InvalidTemplateException('Failed to extract template file.');
        }

        return $tempDir;
    }

    /** @throws InvalidTemplateException */
    private function manifestFrom(string $tempDir): array
    {
        $jsonPath = $tempDir . '/template.json';
        if (!is_file($jsonPath)) {
            throw new InvalidTemplateException('Invalid template file: template.json not found.');
        }

        $data = json_decode(file_get_contents($jsonPath), true);
        if (!is_array($data) || ($data['template_type'] ?? null) !== 'project') {
            throw new InvalidTemplateException('Invalid template file: not a project template.');
        }

        return $data;
    }

    private function log(User $user, string $entityType, int $entityId, string $description): void
    {
        ChangeLog::create([
            'date'        => now(),
            'user_id'     => $user->id,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'description' => $description,
        ]);
    }

    /** Copy a private-disk file into $dir, de-duplicating its name. Returns the name used, or null if missing. */
    private function copyIntoDir(string $diskPath, string $dir): ?string
    {
        if (!Storage::disk('private')->exists($diskPath)) {
            return null;
        }

        $filename  = basename($diskPath);
        $base      = pathinfo($filename, PATHINFO_FILENAME);
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $counter   = 1;
        while (file_exists($dir . '/' . $filename)) {
            $filename = $base . '_' . $counter++ . ($extension !== '' ? '.' . $extension : '');
        }

        copy(Storage::disk('private')->path($diskPath), $dir . '/' . $filename);

        return $filename;
    }

    private function makeTempDir(string $prefix): string
    {
        $dir = storage_path('app/temp/' . $prefix . '_' . uniqid());
        mkdir($dir, 0755, true);

        return $dir;
    }

    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (array_diff(scandir($dir), ['.', '..']) as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) && !is_link($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
