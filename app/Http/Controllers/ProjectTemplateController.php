<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\ChangeLog;
use App\Models\Project;
use App\Models\ProjectTemplate;
use App\Models\ScheduledProject;
use App\Models\Tag;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Services\DateParser;
use App\Services\SafeZipExtractor;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProjectTemplateController extends Controller
{
    /**
     * List templates: the current user's own templates, then public templates
     * created by others.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $myTemplates = ProjectTemplate::where('created_by', $user->id)
            ->orderBy('name')
            ->with('creator')
            ->get();

        $publicTemplates = ProjectTemplate::where('is_public', true)
            ->where('created_by', '!=', $user->id)
            ->orderBy('name')
            ->with('creator')
            ->get();

        return view('templates.index', compact('myTemplates', 'publicTemplates'));
    }

    /**
     * Save a project as a stored template (zip on disk + DB record).
     * Only the project creator may do this.
     */
    public function store(Request $request, Project $project)
    {
        $request->validate([
            'template_name'        => 'required|string|max:255',
            'template_description' => 'nullable|string|max:1000',
            'is_public'            => 'nullable|boolean',
        ]);

        $user = $request->user();

        if ($project->user_id !== $user->id) {
            abort(403, 'Only the project creator can save it as a template.');
        }

        $zipPath = $this->buildTemplateZip($project);

        if ($zipPath === false) {
            return back()->with('error', 'Failed to create template archive. Please ensure the zip utility is installed on the server.');
        }

        // Move zip to permanent storage
        $storedFilename = 'project-templates/' . uniqid('tpl_') . '.zip';
        Storage::disk('private')->put($storedFilename, file_get_contents($zipPath));
        unlink($zipPath);

        ProjectTemplate::create([
            'name'        => $request->template_name,
            'description' => $request->template_description,
            'filename'    => $storedFilename,
            'created_by'  => $user->id,
            'is_public'   => $request->boolean('is_public', false),
        ]);

        return back()->with('status', 'Template "' . $request->template_name . '" saved successfully.');
    }

    /**
     * Create a new project from a stored template.
     */
    public function createFromTemplate(Request $request, ProjectTemplate $template)
    {
        $request->validate([
            'project_name' => 'required|string|max:255',
            'start_date'   => 'nullable|string|max:255',
        ]);

        $user = $request->user();

        if (!$template->is_public && $template->created_by !== $user->id) {
            abort(403, 'You do not have access to this template.');
        }

        // Parse start_date (natural language or blank = today)
        $startDate = null;
        if ($request->filled('start_date')) {
            $parsed = (new DateParser())->parseTaskInput($request->start_date);
            $startDate = $parsed['date'] ?? null;
        }

        $today = now()->toDateString();

        // If start_date is in the future, schedule instead of creating immediately
        if ($startDate && $startDate > $today) {
            ScheduledProject::create([
                'template_id'  => $template->id,
                'user_id'      => $user->id,
                'project_name' => $request->project_name,
                'start_date'   => $startDate,
            ]);

            $formatted = Carbon::parse($startDate)->format('l, F j, Y');
            return redirect()->route('templates.index')
                ->with('status', 'Project "' . $request->project_name . '" scheduled to be created on ' . $formatted . '.');
        }

        // Create immediately
        $zipPath = Storage::disk('private')->path($template->filename);

        if (!file_exists($zipPath)) {
            return back()->with('error', 'Template file not found on the server.');
        }

        $project = $this->createProjectFromZip($zipPath, $request->project_name, $user, $template->id);

        if ($project === false) {
            return back()->with('error', 'Failed to create project from template.');
        }

        return redirect()->route('projects.show', $project)
            ->with('status', 'Project created from template "' . $template->name . '".');
    }

    /**
     * Rename a stored template. Only the creator may rename.
     */
    public function updateName(Request $request, ProjectTemplate $template)
    {
        if ($template->created_by !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Only the template creator can rename it.'], 403);
        }

        $name = trim((string) $request->input('name'));

        if ($name === '') {
            return response()->json(['success' => false, 'message' => 'Name cannot be empty'], 400);
        }
        if (strlen($name) > 255) {
            return response()->json(['success' => false, 'message' => 'Name cannot exceed 255 characters.'], 422);
        }

        $template->update(['name' => $name]);

        return response()->json(['success' => true, 'name' => $template->name]);
    }

    /**
     * Delete a stored template (zip + DB record). Only the creator may delete.
     */
    public function destroy(Request $request, ProjectTemplate $template)
    {
        if ($template->created_by !== $request->user()->id) {
            abort(403, 'You can only delete your own templates.');
        }

        if (Storage::disk('private')->exists($template->filename)) {
            Storage::disk('private')->delete($template->filename);
        }

        $template->delete();

        return back()->with('status', 'Template deleted.');
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Build a project template zip in a temp location.
     * Returns the path to the zip on success, false on failure.
     */
    private function buildTemplateZip(Project $project): string|false
    {
        $data = [
            'exported_at'   => now()->toIso8601String(),
            'template_type' => 'project',
            'project'       => [
                'name'             => $project->name,
                'description'      => $project->description,
                'background_image' => $project->background_image,
            ],
            'tasks'            => [],
            'tags'             => [],
            'task_attachments' => [],
        ];

        $tasks = Task::where('project_id', $project->id)
            ->where('status', 'incomplete')
            ->get();

        $taskIdToIndex = $tasks->values()->mapWithKeys(function ($task, $index) {
            return [$task->id => $index];
        })->all();

        $tagIds             = [];
        $taskAttachmentPaths = [];

        foreach ($tasks as $task) {
            $data['tasks'][] = [
                'name'               => $task->name,
                'description'        => $task->description,
                'date'               => null,
                'time'               => null,
                'location'           => $task->location,
                'recurrence_pattern' => $task->recurrence_pattern,
                'parent_index'       => isset($taskIdToIndex[$task->parent_id]) ? $taskIdToIndex[$task->parent_id] : null,
                'tags'               => $task->tags->pluck('id')->toArray(),
                'assignees'          => [],
            ];

            foreach ($task->tags as $tag) {
                if (!in_array($tag->id, $tagIds)) {
                    $tagIds[] = $tag->id;
                }
            }

            foreach ($task->attachments as $attachment) {
                $data['task_attachments'][] = [
                    'task_index' => count($data['tasks']) - 1,
                    'filename'   => $attachment->original_filename,
                    'path'       => $attachment->file_path,
                ];
                $taskAttachmentPaths[] = $attachment->file_path;
            }
        }

        $tags = Tag::whereIn('id', $tagIds)->get();
        foreach ($tags as $tag) {
            $data['tags'][] = [
                'id'    => $tag->id,
                'name'  => $tag->tag_name,
                'color' => $tag->color,
            ];
        }

        $tempDir = storage_path('app/temp/template_save_' . $project->id . '_' . time());
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        file_put_contents($tempDir . '/template.json', json_encode($data, JSON_PRETTY_PRINT));

        $attachmentsDir = $tempDir . '/attachments';
        mkdir($attachmentsDir, 0755, true);

        foreach ($taskAttachmentPaths as $path) {
            if (Storage::disk('private')->exists($path)) {
                $filename         = basename($path);
                $destPath         = $attachmentsDir . '/' . $filename;
                $counter          = 1;
                $originalFilename = pathinfo($filename, PATHINFO_FILENAME);
                $extension        = pathinfo($filename, PATHINFO_EXTENSION);

                while (file_exists($destPath)) {
                    $filename = $originalFilename . '_' . $counter . '.' . $extension;
                    $destPath = $attachmentsDir . '/' . $filename;
                    $counter++;
                }

                copy(Storage::disk('private')->path($path), $destPath);
            }
        }

        if ($project->background_image && Storage::disk('private')->exists($project->background_image)) {
            $bgDir = $tempDir . '/project-backgrounds';
            mkdir($bgDir, 0755, true);
            copy(
                Storage::disk('private')->path($project->background_image),
                $bgDir . '/' . basename($project->background_image)
            );
        }

        $zipPath    = storage_path('app/temp/tpl_save_' . $project->id . '_' . time() . '.zip');
        $returnCode = 0;
        exec('cd ' . escapeshellarg($tempDir) . ' && zip -r ' . escapeshellarg($zipPath) . ' .', $_, $returnCode);

        $this->deleteDirectory($tempDir);

        return $returnCode === 0 ? $zipPath : false;
    }

    /**
     * Create a project from a template zip file.
     * Returns the new Project on success, false on failure.
     */
    private function createProjectFromZip(string $zipPath, string $projectName, $user, int $templateId): Project|false
    {
        $tempDir = storage_path('app/temp/template_load_' . $user->id . '_' . time());
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        if (!SafeZipExtractor::extract($zipPath, $tempDir)) {
            $this->deleteDirectory($tempDir);
            return false;
        }

        $jsonPath = $tempDir . '/template.json';
        if (!file_exists($jsonPath)) {
            $this->deleteDirectory($tempDir);
            return false;
        }

        $data = json_decode(file_get_contents($jsonPath), true);

        if (!isset($data['template_type']) || $data['template_type'] !== 'project') {
            $this->deleteDirectory($tempDir);
            return false;
        }

        // Create the project
        $project = Project::create([
            'name'        => $projectName,
            'description' => $data['project']['description'] ?? '',
            'user_id'     => $user->id,
            'template_id' => $templateId,
        ]);

        // Log project creation
        ChangeLog::create([
            'date'        => now(),
            'user_id'     => $user->id,
            'entity_type' => 'projects',
            'entity_id'   => $project->id,
            'description' => 'created project from template "' . ProjectTemplate::find($templateId)?->name . '"',
        ]);

        // Import background image
        $bgDir = $tempDir . '/project-backgrounds';
        if (!empty($data['project']['background_image'])) {
            $bgFilename = basename($data['project']['background_image']);
            $sourceFile = $bgDir . '/' . $bgFilename;
            if (file_exists($sourceFile)) {
                $newBgPath = 'project-backgrounds/' . $project->id . '/' . $bgFilename;
                Storage::disk('private')->put($newBgPath, file_get_contents($sourceFile));
                $project->update(['background_image' => $newBgPath]);
            }
        }

        // Map old tag IDs to existing/new tags
        $tagIdMap = [];
        foreach ($data['tags'] ?? [] as $tagData) {
            $existing = Tag::find($tagData['id']);
            if ($existing) {
                $tagIdMap[$tagData['id']] = $existing->id;
            } else {
                $newTag                   = Tag::create([
                    'id'       => $tagData['id'],
                    'tag_name' => $tagData['name'],
                    'color'    => $tagData['color'],
                ]);
                $tagIdMap[$tagData['id']] = $newTag->id;
            }
        }

        // Create tasks — track index → new task ID so we can wire up parent_id afterwards
        $attachmentsDir = $tempDir . '/attachments';
        $indexToTaskId  = [];
        foreach ($data['tasks'] ?? [] as $index => $taskData) {
            $task = Task::create([
                'name'               => $taskData['name'],
                'description'        => $taskData['description'],
                'status'             => 'incomplete',
                'date'               => $taskData['date'] ?? null,
                'time'               => $taskData['time'] ?? null,
                'location'           => $taskData['location'] ?? null,
                'recurrence_pattern' => $taskData['recurrence_pattern'],
                'project_id'         => $project->id,
                'creator_id'         => $user->id,
            ]);

            $indexToTaskId[$index] = $task->id;

            ChangeLog::create([
                'date'        => now(),
                'user_id'     => $user->id,
                'entity_type' => 'tasks',
                'entity_id'   => $task->id,
                'description' => 'created task via template import',
            ]);

            // Attach tags
            $newTagIds = [];
            foreach ($taskData['tags'] ?? [] as $oldTagId) {
                if (isset($tagIdMap[$oldTagId])) {
                    $newTagIds[] = $tagIdMap[$oldTagId];
                }
            }
            if (!empty($newTagIds)) {
                $task->tags()->attach($newTagIds);
            }

            // Assignments: keep original assignees if they exist, always add importer
            $assigneeIds = $taskData['assignees'] ?? [];
            if (!in_array($user->id, $assigneeIds)) {
                $assigneeIds[] = $user->id;
            }
            foreach ($assigneeIds as $assigneeId) {
                if (\App\Models\User::find($assigneeId)) {
                    Assignment::create([
                        'task_id'         => $task->id,
                        'assignee_id'     => $assigneeId,
                        'assigned_by_id'  => $user->id,
                    ]);
                }
            }

            // Task attachments
            foreach ($data['task_attachments'] ?? [] as $attachmentData) {
                if ($attachmentData['task_index'] === $index) {
                    $sourceFile = $attachmentsDir . '/' . basename($attachmentData['path']);
                    if (file_exists($sourceFile)) {
                        // basename() guards against path traversal in the
                        // zip's JSON-supplied filename
                        $safeFilename = basename($attachmentData['filename']);
                        $newPath = 'task_attachments/' . uniqid() . '_' . $safeFilename;
                        Storage::disk('private')->put($newPath, file_get_contents($sourceFile));
                        TaskAttachment::create([
                            'task_id'           => $task->id,
                            'user_id'           => $user->id,
                            'original_filename' => $safeFilename,
                            'file_path'         => $newPath,
                            'file_size'         => filesize($sourceFile),
                        ]);
                    }
                }
            }
        }

        // Second pass: restore parent/child relationships
        foreach ($data['tasks'] ?? [] as $index => $taskData) {
            $parentIndex = $taskData['parent_index'] ?? null;
            if ($parentIndex !== null && isset($indexToTaskId[$parentIndex], $indexToTaskId[$index])) {
                Task::where('id', $indexToTaskId[$index])
                    ->update(['parent_id' => $indexToTaskId[$parentIndex]]);
            }
        }

        $this->deleteDirectory($tempDir);

        return $project;
    }

    private function deleteDirectory(string $dir): void
    {
        if (!file_exists($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
