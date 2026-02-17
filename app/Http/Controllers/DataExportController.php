<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use ZipArchive;
use App\Models\Project;
use App\Models\Task;
use App\Models\Tag;
use App\Models\Assignment;
use App\Models\Comment;
use App\Models\TaskAttachment;
use App\Models\ChangeLog;

class DataExportController extends Controller
{
    /**
     * Export all user data as a zip file containing JSON and attachments.
     */
    public function exportAll(Request $request)
    {
        $user = $request->user();

        // Gather all user data
        $data = [
            'exported_at' => now()->toIso8601String(),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'projects' => [],
            'tasks' => [],
            'tags' => [],
            'assignments' => [],
            'comments' => [],
            'task_attachments' => [],
            'change_logs' => [],
        ];

        // Get all tasks the user has access to (created by user OR assigned to user)
        $tasks = Task::where('creator_id', $user->id)
            ->orWhereHas('assignees', function($q) use ($user) {
                $q->where('users.id', $user->id);
            })
            ->get();

        // Get all projects the user has access to (created by user OR has assigned tasks in)
        $projectIds = $tasks->pluck('project_id')->filter()->unique();
        $projects = Project::where('user_id', $user->id)
            ->orWhereIn('id', $projectIds)
            ->get();

        foreach ($projects as $project) {
            $data['projects'][] = [
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
                'user_id' => $project->user_id,
                'status' => $project->status,
                'created_at' => $project->created_at->toIso8601String(),
                'updated_at' => $project->updated_at->toIso8601String(),
            ];
        }
        foreach ($tasks as $task) {
            $data['tasks'][] = [
                'id' => $task->id,
                'name' => $task->name,
                'description' => $task->description,
                'status' => $task->status,
                'date' => $task->date,
                'time' => $task->time,
                'recurrence_pattern' => $task->recurrence_pattern,
                'project_id' => $task->project_id,
                'creator_id' => $task->creator_id,
                'created_at' => $task->created_at->toIso8601String(),
                'updated_at' => $task->updated_at->toIso8601String(),
                'tags' => $task->tags->pluck('id')->toArray(),
            ];
        }

        // Get all tags used on user's tasks
        $tagIds = $tasks->flatMap(function($task) {
            return $task->tags->pluck('id');
        })->unique();

        $tags = Tag::whereIn('id', $tagIds)->get();
        foreach ($tags as $tag) {
            $data['tags'][] = [
                'id' => $tag->id,
                'name' => $tag->tag_name,
                'color' => $tag->color,
                'created_at' => $tag->created_at->toIso8601String(),
                'updated_at' => $tag->updated_at->toIso8601String(),
            ];
        }

        // Get all assignments for user's tasks
        $taskIds = $tasks->pluck('id');
        $assignments = Assignment::whereIn('task_id', $taskIds)->get();
        foreach ($assignments as $assignment) {
            $data['assignments'][] = [
                'id' => $assignment->id,
                'task_id' => $assignment->task_id,
                'assignee_id' => $assignment->assignee_id,
                'assigned_by_id' => $assignment->assigned_by_id,
                'created_at' => $assignment->created_at->toIso8601String(),
                'updated_at' => $assignment->updated_at->toIso8601String(),
            ];
        }

        // Get all comments on user's tasks
        $comments = Comment::whereIn('task_id', $taskIds)->get();
        $commentAttachmentPaths = [];
        foreach ($comments as $comment) {
            $data['comments'][] = [
                'id' => $comment->id,
                'task_id' => $comment->task_id,
                'user_id' => $comment->user_id,
                'content' => $comment->content,
                'attachment_path' => $comment->attachment_path,
                'created_at' => $comment->created_at->toIso8601String(),
                'updated_at' => $comment->updated_at->toIso8601String(),
            ];

            if ($comment->attachment_path) {
                $commentAttachmentPaths[] = $comment->attachment_path;
            }
        }

        // Get all task attachments for user's tasks
        $taskAttachments = TaskAttachment::whereIn('task_id', $taskIds)->get();
        $taskAttachmentPaths = [];
        foreach ($taskAttachments as $attachment) {
            $data['task_attachments'][] = [
                'id' => $attachment->id,
                'task_id' => $attachment->task_id,
                'filename' => $attachment->filename,
                'path' => $attachment->path,
                'created_at' => $attachment->created_at->toIso8601String(),
                'updated_at' => $attachment->updated_at->toIso8601String(),
            ];

            if ($attachment->path) {
                $taskAttachmentPaths[] = $attachment->path;
            }
        }

        // Get all change logs related to user's tasks, projects, and tags
        $projectIds = $projects->pluck('id');
        $tagIds = $tags->pluck('id');

        $changeLogs = ChangeLog::where(function($query) use ($taskIds, $projectIds, $tagIds) {
            $query->where(function($q) use ($taskIds) {
                $q->where('entity_type', 'task')
                  ->whereIn('entity_id', $taskIds);
            })
            ->orWhere(function($q) use ($projectIds) {
                $q->where('entity_type', 'project')
                  ->whereIn('entity_id', $projectIds);
            })
            ->orWhere(function($q) use ($tagIds) {
                $q->where('entity_type', 'tag')
                  ->whereIn('entity_id', $tagIds);
            });
        })->get();

        foreach ($changeLogs as $log) {
            $data['change_logs'][] = [
                'id' => $log->id,
                'date' => $log->date,
                'user_id' => $log->user_id,
                'entity_type' => $log->entity_type,
                'entity_id' => $log->entity_id,
                'description' => $log->description,
                'created_at' => $log->created_at->toIso8601String(),
                'updated_at' => $log->updated_at->toIso8601String(),
            ];
        }

        // Create temporary directory for export
        $tempDir = storage_path('app/temp/export_' . $user->id . '_' . time());
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        // Write JSON file
        $jsonPath = $tempDir . '/data.json';
        file_put_contents($jsonPath, json_encode($data, JSON_PRETTY_PRINT));

        // Create attachments directory
        $attachmentsDir = $tempDir . '/attachments';
        if (!file_exists($attachmentsDir)) {
            mkdir($attachmentsDir, 0755, true);
        }

        // Copy all attachment files
        $allAttachmentPaths = array_merge($taskAttachmentPaths, $commentAttachmentPaths);
        foreach ($allAttachmentPaths as $path) {
            if (Storage::disk('private')->exists($path)) {
                $filename = basename($path);
                $destPath = $attachmentsDir . '/' . $filename;

                // Handle duplicate filenames by adding a counter
                $counter = 1;
                $originalFilename = pathinfo($filename, PATHINFO_FILENAME);
                $extension = pathinfo($filename, PATHINFO_EXTENSION);

                while (file_exists($destPath)) {
                    $filename = $originalFilename . '_' . $counter . '.' . $extension;
                    $destPath = $attachmentsDir . '/' . $filename;
                    $counter++;
                }

                copy(Storage::disk('private')->path($path), $destPath);
            }
        }

        // Create zip file
        $zipPath = storage_path('app/temp/taskfiend_export_' . $user->id . '_' . time() . '.zip');
        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            // Add JSON file
            $zip->addFile($jsonPath, 'data.json');

            // Add all attachment files
            if (is_dir($attachmentsDir)) {
                $files = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($attachmentsDir),
                    \RecursiveIteratorIterator::LEAVES_ONLY
                );

                foreach ($files as $file) {
                    if (!$file->isDir()) {
                        $filePath = $file->getRealPath();
                        $relativePath = 'attachments/' . basename($filePath);
                        $zip->addFile($filePath, $relativePath);
                    }
                }
            }

            $zip->close();
        }

        // Clean up temp directory
        $this->deleteDirectory($tempDir);

        // Return zip file as download
        return response()->download($zipPath, 'taskfiend_export_' . now()->format('Y-m-d_His') . '.zip')->deleteFileAfterSend(true);
    }

    /**
     * Import user data from a zip file.
     */
    public function importAll(Request $request)
    {
        $request->validate([
            'import_file' => 'required|file|mimes:zip',
        ]);

        $user = $request->user();

        // Create temp directory for extraction
        $tempDir = storage_path('app/temp/import_' . $user->id . '_' . time());
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        // Save uploaded file
        $zipPath = $request->file('import_file')->path();

        // Extract zip
        $zip = new ZipArchive();
        if ($zip->open($zipPath) === true) {
            $zip->extractTo($tempDir);
            $zip->close();
        } else {
            return back()->with('error', 'Failed to extract zip file.');
        }

        // Read JSON data
        $jsonPath = $tempDir . '/data.json';
        if (!file_exists($jsonPath)) {
            $this->deleteDirectory($tempDir);
            return back()->with('error', 'Invalid export file: data.json not found.');
        }

        $data = json_decode(file_get_contents($jsonPath), true);

        // Import projects
        foreach ($data['projects'] ?? [] as $projectData) {
            $project = Project::find($projectData['id']);
            if ($project) {
                // Update existing project
                $project->update([
                    'name' => $projectData['name'],
                    'description' => $projectData['description'],
                    'user_id' => $projectData['user_id'],
                ]);
            } else {
                // Create new project with original ID
                Project::create([
                    'id' => $projectData['id'],
                    'name' => $projectData['name'],
                    'description' => $projectData['description'],
                    'user_id' => $projectData['user_id'],
                ]);
            }
        }

        // Import tags
        foreach ($data['tags'] ?? [] as $tagData) {
            $tag = Tag::find($tagData['id']);
            if ($tag) {
                // Update existing tag
                $tag->update([
                    'tag_name' => $tagData['name'],
                    'color' => $tagData['color'],
                ]);
            } else {
                // Create new tag with original ID
                Tag::create([
                    'id' => $tagData['id'],
                    'tag_name' => $tagData['name'],
                    'color' => $tagData['color'],
                ]);
            }
        }

        // Import tasks
        foreach ($data['tasks'] ?? [] as $taskData) {
            $tagIds = $taskData['tags'] ?? [];
            unset($taskData['tags']);

            $task = Task::find($taskData['id']);
            if ($task) {
                // Update existing task
                $task->update([
                    'name' => $taskData['name'],
                    'description' => $taskData['description'],
                    'status' => $taskData['status'],
                    'date' => $taskData['date'] ?? null,
                    'time' => $taskData['time'] ?? null,
                    'recurrence_pattern' => $taskData['recurrence_pattern'],
                    'project_id' => $taskData['project_id'],
                    'creator_id' => $taskData['creator_id'],
                ]);
            } else {
                // Create new task with original ID
                $task = Task::create([
                    'id' => $taskData['id'],
                    'name' => $taskData['name'],
                    'description' => $taskData['description'],
                    'status' => $taskData['status'],
                    'date' => $taskData['date'] ?? null,
                    'time' => $taskData['time'] ?? null,
                    'recurrence_pattern' => $taskData['recurrence_pattern'],
                    'project_id' => $taskData['project_id'],
                    'creator_id' => $taskData['creator_id'],
                ]);

                // Log task creation
                $task->changeLogs()->create([
                    'date' => now(),
                    'user_id' => $user->id,
                    'entity_type' => 'tasks',
                    'entity_id' => $task->id,
                    'description' => $user->name . ' created task via data import',
                ]);
            }

            // Sync tags
            $task->tags()->sync($tagIds);
        }

        // Import assignments
        foreach ($data['assignments'] ?? [] as $assignmentData) {
            $assignment = Assignment::find($assignmentData['id']);
            if ($assignment) {
                // Update existing assignment
                $assignment->update([
                    'task_id' => $assignmentData['task_id'],
                    'assignee_id' => $assignmentData['assignee_id'],
                    'assigned_by_id' => $assignmentData['assigned_by_id'],
                ]);
            } else {
                // Create new assignment with original ID
                Assignment::create([
                    'id' => $assignmentData['id'],
                    'task_id' => $assignmentData['task_id'],
                    'assignee_id' => $assignmentData['assignee_id'],
                    'assigned_by_id' => $assignmentData['assigned_by_id'],
                ]);
            }
        }

        // Import task attachments
        $attachmentsDir = $tempDir . '/attachments';
        foreach ($data['task_attachments'] ?? [] as $attachmentData) {
            $attachment = TaskAttachment::find($attachmentData['id']);

            // Copy file to storage if it exists in the import
            $sourceFile = $attachmentsDir . '/' . basename($attachmentData['path']);
            if (file_exists($sourceFile)) {
                Storage::disk('private')->put($attachmentData['path'], file_get_contents($sourceFile));
            }

            if ($attachment) {
                // Update existing attachment
                $attachment->update([
                    'task_id' => $attachmentData['task_id'],
                    'filename' => $attachmentData['filename'],
                    'path' => $attachmentData['path'],
                ]);
            } else {
                // Create new attachment with original ID
                TaskAttachment::create([
                    'id' => $attachmentData['id'],
                    'task_id' => $attachmentData['task_id'],
                    'filename' => $attachmentData['filename'],
                    'path' => $attachmentData['path'],
                ]);
            }
        }

        // Import comments
        foreach ($data['comments'] ?? [] as $commentData) {
            $comment = Comment::find($commentData['id']);

            // Copy attachment file to storage if it exists in the import
            if ($commentData['attachment_path']) {
                $sourceFile = $attachmentsDir . '/' . basename($commentData['attachment_path']);
                if (file_exists($sourceFile)) {
                    Storage::disk('private')->put($commentData['attachment_path'], file_get_contents($sourceFile));
                }
            }

            if ($comment) {
                // Update existing comment
                $comment->update([
                    'task_id' => $commentData['task_id'],
                    'user_id' => $commentData['user_id'],
                    'content' => $commentData['content'],
                    'attachment_path' => $commentData['attachment_path'],
                ]);
            } else {
                // Create new comment with original ID
                Comment::create([
                    'id' => $commentData['id'],
                    'task_id' => $commentData['task_id'],
                    'user_id' => $commentData['user_id'],
                    'content' => $commentData['content'],
                    'attachment_path' => $commentData['attachment_path'],
                ]);
            }
        }

        // Import change logs
        foreach ($data['change_logs'] ?? [] as $logData) {
            $log = ChangeLog::find($logData['id']);
            if ($log) {
                // Update existing log
                $log->update([
                    'entity_type' => $logData['entity_type'],
                    'entity_id' => $logData['entity_id'],
                    'user_id' => $logData['user_id'],
                    'action' => $logData['action'],
                    'changes' => $logData['changes'],
                ]);
            } else {
                // Create new log with original ID
                ChangeLog::create([
                    'id' => $logData['id'],
                    'entity_type' => $logData['entity_type'],
                    'entity_id' => $logData['entity_id'],
                    'user_id' => $logData['user_id'],
                    'action' => $logData['action'],
                    'changes' => $logData['changes'],
                ]);
            }
        }

        // Clean up temp directory
        $this->deleteDirectory($tempDir);

        return back()->with('status', 'Data imported successfully!');
    }

    /**
     * Export a single project as a template (incomplete tasks only).
     */
    public function exportProjectTemplate(Request $request, Project $project)
    {
        $user = $request->user();

        // Check authorization - user must be creator or assignee
        $isCreator = $project->user_id === $user->id;
        $isAssignee = $project->tasks()->whereHas('assignments', function($q) use ($user) {
            $q->where('user_id', $user->id);
        })->exists();

        if (!$isCreator && !$isAssignee) {
            abort(403, 'You do not have permission to export this project.');
        }

        // Gather project data
        $data = [
            'exported_at' => now()->toIso8601String(),
            'template_type' => 'project',
            'project' => [
                'name' => $project->name,
                'description' => $project->description,
            ],
            'tasks' => [],
            'tags' => [],
            'task_attachments' => [],
        ];

        // Get only incomplete tasks for this project
        $tasks = Task::where('project_id', $project->id)
            ->where('status', 'incomplete')
            ->get();

        $tagIds = [];
        $taskAttachmentPaths = [];

        foreach ($tasks as $task) {
            $data['tasks'][] = [
                'name' => $task->name,
                'description' => $task->description,
                'date' => $task->date,
                'time' => $task->time,
                'recurrence_pattern' => $task->recurrence_pattern,
                'tags' => $task->tags->pluck('id')->toArray(),
                'assignees' => $task->assignments->pluck('assignee_id')->toArray(),
            ];

            // Collect tag IDs
            foreach ($task->tags as $tag) {
                if (!in_array($tag->id, $tagIds)) {
                    $tagIds[] = $tag->id;
                }
            }

            // Get task attachments
            foreach ($task->attachments as $attachment) {
                $data['task_attachments'][] = [
                    'task_index' => count($data['tasks']) - 1,
                    'filename' => $attachment->filename,
                    'path' => $attachment->path,
                ];
                $taskAttachmentPaths[] = $attachment->path;
            }
        }

        // Get all tags used in tasks
        $tags = Tag::whereIn('id', $tagIds)->get();
        foreach ($tags as $tag) {
            $data['tags'][] = [
                'id' => $tag->id,
                'name' => $tag->name,
                'color' => $tag->color,
            ];
        }

        // Create temporary directory for export
        $tempDir = storage_path('app/temp/template_export_' . $project->id . '_' . time());
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        // Write JSON file
        $jsonPath = $tempDir . '/template.json';
        file_put_contents($jsonPath, json_encode($data, JSON_PRETTY_PRINT));

        // Create attachments directory
        $attachmentsDir = $tempDir . '/attachments';
        if (!file_exists($attachmentsDir)) {
            mkdir($attachmentsDir, 0755, true);
        }

        // Copy all attachment files
        foreach ($taskAttachmentPaths as $path) {
            if (Storage::disk('private')->exists($path)) {
                $filename = basename($path);
                $destPath = $attachmentsDir . '/' . $filename;

                // Handle duplicate filenames by adding a counter
                $counter = 1;
                $originalFilename = pathinfo($filename, PATHINFO_FILENAME);
                $extension = pathinfo($filename, PATHINFO_EXTENSION);

                while (file_exists($destPath)) {
                    $filename = $originalFilename . '_' . $counter . '.' . $extension;
                    $destPath = $attachmentsDir . '/' . $filename;
                    $counter++;
                }

                copy(Storage::disk('private')->path($path), $destPath);
            }
        }

        // Create zip file
        $zipPath = storage_path('app/temp/taskfiend_template_' . $project->id . '_' . time() . '.zip');
        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            // Add JSON file
            $zip->addFile($jsonPath, 'template.json');

            // Add all attachment files
            if (is_dir($attachmentsDir)) {
                $files = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($attachmentsDir),
                    \RecursiveIteratorIterator::LEAVES_ONLY
                );

                foreach ($files as $file) {
                    if (!$file->isDir()) {
                        $filePath = $file->getRealPath();
                        $relativePath = 'attachments/' . basename($filePath);
                        $zip->addFile($filePath, $relativePath);
                    }
                }
            }

            $zip->close();
        }

        // Clean up temp directory
        $this->deleteDirectory($tempDir);

        // Return zip file as download
        return response()->download($zipPath, 'taskfiend_template_' . str_replace(' ', '_', $project->name) . '_' . now()->format('Y-m-d') . '.zip')->deleteFileAfterSend(true);
    }

    /**
     * Import a project template and create a new project.
     */
    public function importProjectTemplate(Request $request)
    {
        $request->validate([
            'template_file' => 'required|file|mimes:zip',
            'project_name' => 'required|string|max:255',
        ]);

        $user = $request->user();

        // Create temp directory for extraction
        $tempDir = storage_path('app/temp/template_import_' . $user->id . '_' . time());
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        // Save uploaded file
        $zipPath = $request->file('template_file')->path();

        // Extract zip
        $zip = new ZipArchive();
        if ($zip->open($zipPath) === true) {
            $zip->extractTo($tempDir);
            $zip->close();
        } else {
            return back()->with('error', 'Failed to extract template file.');
        }

        // Read JSON data
        $jsonPath = $tempDir . '/template.json';
        if (!file_exists($jsonPath)) {
            $this->deleteDirectory($tempDir);
            return back()->with('error', 'Invalid template file: template.json not found.');
        }

        $data = json_decode(file_get_contents($jsonPath), true);

        // Verify it's a project template
        if (!isset($data['template_type']) || $data['template_type'] !== 'project') {
            $this->deleteDirectory($tempDir);
            return back()->with('error', 'Invalid template file: not a project template.');
        }

        // Create new project
        $project = Project::create([
            'name' => $request->project_name,
            'description' => $data['project']['description'] ?? '',
            'user_id' => $user->id,
        ]);

        // Map old tag IDs to existing/new tag IDs
        $tagIdMap = [];
        foreach ($data['tags'] ?? [] as $tagData) {
            // Try to find existing tag by ID first
            $existingTag = Tag::find($tagData['id']);
            if ($existingTag) {
                $tagIdMap[$tagData['id']] = $existingTag->id;
            } else {
                // Create new tag with original ID if it doesn't exist
                $newTag = Tag::create([
                    'id' => $tagData['id'],
                    'tag_name' => $tagData['name'],
                    'color' => $tagData['color'],
                ]);
                $tagIdMap[$tagData['id']] = $newTag->id;
            }
        }

        // Create tasks
        $attachmentsDir = $tempDir . '/attachments';
        foreach ($data['tasks'] ?? [] as $index => $taskData) {
            $task = Task::create([
                'name' => $taskData['name'],
                'description' => $taskData['description'],
                'status' => 'incomplete',
                'date' => $taskData['date'] ?? null,
                'time' => $taskData['time'] ?? null,
                'recurrence_pattern' => $taskData['recurrence_pattern'],
                'project_id' => $project->id,
                'creator_id' => $user->id,
            ]);

            // Log task creation
            $task->changeLogs()->create([
                'date' => now(),
                'user_id' => $user->id,
                'entity_type' => 'tasks',
                'entity_id' => $task->id,
                'description' => $user->name . ' created task via template import',
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

            // Create assignments (keep original assignees + add importer)
            $assigneeIds = $taskData['assignees'] ?? [];
            if (!in_array($user->id, $assigneeIds)) {
                $assigneeIds[] = $user->id;
            }

            foreach ($assigneeIds as $assigneeId) {
                // Only create assignment if user exists
                if (\App\Models\User::find($assigneeId)) {
                    Assignment::create([
                        'task_id' => $task->id,
                        'assignee_id' => $assigneeId,
                        'assigned_by_id' => $user->id,
                    ]);
                }
            }

            // Import attachments for this task
            foreach ($data['task_attachments'] ?? [] as $attachmentData) {
                if ($attachmentData['task_index'] === $index) {
                    $sourceFile = $attachmentsDir . '/' . basename($attachmentData['path']);

                    if (file_exists($sourceFile)) {
                        // Generate new unique path
                        $newPath = 'task_attachments/' . uniqid() . '_' . $attachmentData['filename'];
                        Storage::disk('private')->put($newPath, file_get_contents($sourceFile));

                        TaskAttachment::create([
                            'task_id' => $task->id,
                            'filename' => $attachmentData['filename'],
                            'path' => $newPath,
                        ]);
                    }
                }
            }
        }

        // Clean up temp directory
        $this->deleteDirectory($tempDir);

        return redirect()->route('projects.show', $project)->with('status', 'Project template imported successfully!');
    }

    /**
     * Helper function to recursively delete a directory.
     */
    private function deleteDirectory($dir)
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
