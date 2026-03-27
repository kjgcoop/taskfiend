<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
                'profile_image' => $user->profile_image,
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

        $projectBackgroundPaths = [];
        foreach ($projects as $project) {
            $data['projects'][] = [
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
                'background_image' => $project->background_image,
                'user_id' => $project->user_id,
                'status' => $project->status,
                'created_at' => $project->created_at->toIso8601String(),
                'updated_at' => $project->updated_at->toIso8601String(),
            ];

            if ($project->background_image) {
                $projectBackgroundPaths[$project->id] = $project->background_image;
            }
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

        // Copy profile image
        $profileImageDir = $tempDir . '/profile-image';
        if ($user->profile_image && Storage::disk('private')->exists($user->profile_image)) {
            if (!file_exists($profileImageDir)) {
                mkdir($profileImageDir, 0755, true);
            }
            $profileImageFilename = basename($user->profile_image);
            copy(Storage::disk('private')->path($user->profile_image), $profileImageDir . '/' . $profileImageFilename);
        }

        // Copy project background images
        $projectBackgroundsDir = $tempDir . '/project-backgrounds';
        if (!empty($projectBackgroundPaths)) {
            if (!file_exists($projectBackgroundsDir)) {
                mkdir($projectBackgroundsDir, 0755, true);
            }

            foreach ($projectBackgroundPaths as $projectId => $path) {
                if (Storage::disk('private')->exists($path)) {
                    $filename = basename($path);
                    $destPath = $projectBackgroundsDir . '/' . $projectId . '_' . $filename;
                    copy(Storage::disk('private')->path($path), $destPath);
                }
            }
        }

        // Create zip file using the system zip binary (no php-zip extension required)
        $zipPath = storage_path('app/temp/taskfiend_export_' . $user->id . '_' . time() . '.zip');
        $returnCode = 0;
        exec('cd ' . escapeshellarg($tempDir) . ' && zip -r ' . escapeshellarg($zipPath) . ' .', $_, $returnCode);

        if ($returnCode !== 0) {
            $this->deleteDirectory($tempDir);
            return back()->with('error', 'Failed to create export archive. Please ensure the zip utility is installed on the server.');
        }

        // Clean up temp directory
        $this->deleteDirectory($tempDir);

        // Return zip file as download
        return response()->download($zipPath, 'taskfiend_export_' . now()->format('Y-m-d_His') . '.zip')->deleteFileAfterSend(true);
    }

    /**
     * Import user data from a zip file.
     *
     * DISABLED: The importer matches records by original ID with no ownership checks.
     * On a shared server this can silently overwrite other users' data. Needs full
     * ID remapping before it is safe to re-enable.
     */
    public function importAll(Request $request)
    {
        abort(503, 'Data import is temporarily unavailable.');

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

        // Extract zip using the system unzip binary (no php-zip extension required)
        $returnCode = 0;
        exec('unzip -o ' . escapeshellarg($zipPath) . ' -d ' . escapeshellarg($tempDir), $_, $returnCode);

        if ($returnCode !== 0) {
            $this->deleteDirectory($tempDir);
            return back()->with('error', 'Failed to extract zip file.');
        }

        // Read JSON data
        $jsonPath = $tempDir . '/data.json';
        if (!file_exists($jsonPath)) {
            $this->deleteDirectory($tempDir);
            return back()->with('error', 'Invalid export file: data.json not found.');
        }

        $data = json_decode(file_get_contents($jsonPath), true);

        // Restore profile image if present in the export
        $profileImageDir = $tempDir . '/profile-image';
        if (!empty($data['user']['profile_image'])) {
            $profileImageFilename = basename($data['user']['profile_image']);
            $sourceFile = $profileImageDir . '/' . $profileImageFilename;
            if (file_exists($sourceFile)) {
                // Delete existing profile image before replacing
                if ($user->profile_image && Storage::disk('private')->exists($user->profile_image)) {
                    Storage::disk('private')->delete($user->profile_image);
                }
                $newProfileImagePath = 'profile_images/' . $profileImageFilename;
                Storage::disk('private')->put($newProfileImagePath, file_get_contents($sourceFile));
                $user->update(['profile_image' => $newProfileImagePath]);
            }
        }

        // Import projects
        $projectBackgroundsDir = $tempDir . '/project-backgrounds';
        foreach ($data['projects'] ?? [] as $projectData) {
            // Handle background image: copy from export ZIP to private storage
            $backgroundImagePath = null;
            if (!empty($projectData['background_image'])) {
                $exportedFilename = $projectData['id'] . '_' . basename($projectData['background_image']);
                $sourceFile = $projectBackgroundsDir . '/' . $exportedFilename;
                if (file_exists($sourceFile)) {
                    $storagePath = 'project-backgrounds/' . $projectData['id'] . '/' . basename($projectData['background_image']);
                    Storage::disk('private')->put($storagePath, file_get_contents($sourceFile));
                    $backgroundImagePath = $storagePath;
                }
            }

            $project = Project::find($projectData['id']);
            if ($project) {
                // Update existing project
                $updateData = [
                    'name' => $projectData['name'],
                    'description' => $projectData['description'],
                    'user_id' => $projectData['user_id'],
                    'status' => $projectData['status'] ?? 'incomplete',
                ];
                if ($backgroundImagePath !== null) {
                    $updateData['background_image'] = $backgroundImagePath;
                }
                $project->update($updateData);
            } else {
                // Create new project with original ID
                Project::create([
                    'id' => $projectData['id'],
                    'name' => $projectData['name'],
                    'description' => $projectData['description'],
                    'background_image' => $backgroundImagePath,
                    'user_id' => $projectData['user_id'],
                    'status' => $projectData['status'] ?? 'incomplete',
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
                    'description' => 'created task via data import',
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
                    'date' => $logData['date'],
                    'entity_type' => $logData['entity_type'],
                    'entity_id' => $logData['entity_id'],
                    'user_id' => $logData['user_id'],
                    'description' => $logData['description'],
                ]);
            } else {
                // Create new log with original ID
                ChangeLog::create([
                    'id' => $logData['id'],
                    'date' => $logData['date'],
                    'entity_type' => $logData['entity_type'],
                    'entity_id' => $logData['entity_id'],
                    'user_id' => $logData['user_id'],
                    'description' => $logData['description'],
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
            $q->where('assignee_id', $user->id);
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
                'background_image' => $project->background_image,
            ],
            'tasks' => [],
            'tags' => [],
            'task_attachments' => [],
        ];

        // Get only incomplete tasks for this project
        $tasks = Task::where('project_id', $project->id)
            ->where('status', 'incomplete')
            ->get();

        // Build a map from task ID to its position in the export array so we
        // can store parent_index for subtasks.
        $taskIdToIndex = $tasks->values()->mapWithKeys(function ($task, $index) {
            return [$task->id => $index];
        })->all();

        $tagIds = [];
        $taskAttachmentPaths = [];

        foreach ($tasks as $task) {
            $data['tasks'][] = [
                'name' => $task->name,
                'description' => $task->description,
                'date' => $task->date,
                'time' => $task->time,
                'recurrence_pattern' => $task->recurrence_pattern,
                'parent_index' => isset($taskIdToIndex[$task->parent_id]) ? $taskIdToIndex[$task->parent_id] : null,
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
                'name' => $tag->tag_name,
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

        // Copy project background image if it exists
        $projectBackgroundsDir = $tempDir . '/project-backgrounds';
        if ($project->background_image && Storage::disk('private')->exists($project->background_image)) {
            if (!file_exists($projectBackgroundsDir)) {
                mkdir($projectBackgroundsDir, 0755, true);
            }
            $bgFilename = basename($project->background_image);
            copy(Storage::disk('private')->path($project->background_image), $projectBackgroundsDir . '/' . $bgFilename);
        }

        // Create zip file using the system zip binary (no php-zip extension required)
        $zipPath = storage_path('app/temp/taskfiend_template_' . $project->id . '_' . time() . '.zip');
        $returnCode = 0;
        exec('cd ' . escapeshellarg($tempDir) . ' && zip -r ' . escapeshellarg($zipPath) . ' .', $_, $returnCode);

        if ($returnCode !== 0) {
            $this->deleteDirectory($tempDir);
            return back()->with('error', 'Failed to create export archive. Please ensure the zip utility is installed on the server.');
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

        // Extract zip using the system unzip binary (no php-zip extension required)
        $returnCode = 0;
        exec('unzip -o ' . escapeshellarg($zipPath) . ' -d ' . escapeshellarg($tempDir), $_, $returnCode);

        if ($returnCode !== 0) {
            $this->deleteDirectory($tempDir);
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

        // Import project background image if present
        $projectBackgroundsDir = $tempDir . '/project-backgrounds';
        if (!empty($data['project']['background_image'])) {
            $bgFilename = basename($data['project']['background_image']);
            $sourceFile = $projectBackgroundsDir . '/' . $bgFilename;
            if (file_exists($sourceFile)) {
                $newBgPath = 'project-backgrounds/' . $project->id . '/' . $bgFilename;
                Storage::disk('private')->put($newBgPath, file_get_contents($sourceFile));
                $project->update(['background_image' => $newBgPath]);
            }
        }

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

        // Create tasks — track index → new task ID so we can wire up parent_id afterwards
        $attachmentsDir = $tempDir . '/attachments';
        $indexToTaskId = [];
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

            $indexToTaskId[$index] = $task->id;

            // Log task creation
            $task->changeLogs()->create([
                'date' => now(),
                'user_id' => $user->id,
                'entity_type' => 'tasks',
                'entity_id' => $task->id,
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

        // Second pass: restore parent/child relationships
        foreach ($data['tasks'] ?? [] as $index => $taskData) {
            $parentIndex = $taskData['parent_index'] ?? null;
            if ($parentIndex !== null && isset($indexToTaskId[$parentIndex], $indexToTaskId[$index])) {
                Task::where('id', $indexToTaskId[$index])
                    ->update(['parent_id' => $indexToTaskId[$parentIndex]]);
            }
        }

        // Clean up temp directory
        $this->deleteDirectory($tempDir);

        return redirect()->route('projects.show', $project)->with('status', 'Project template imported successfully!');
    }

    /**
     * Export a single project as a Markdown file with tasks grouped by status.
     */
    public function exportMarkdown(Request $request, Project $project)
    {
        $user = $request->user();

        $isCreator = $project->user_id === $user->id;
        $isAssignee = $project->tasks()->whereHas('assignments', function($q) use ($user) {
            $q->where('assignee_id', $user->id);
        })->exists();

        if (!$isCreator && !$isAssignee) {
            abort(403, 'You do not have permission to export this project.');
        }

        // Load all top-level tasks with their children
        $tasks = Task::where('project_id', $project->id)
            ->whereNull('parent_id')
            ->orderBy('created_at')
            ->with(['children' => function($q) {
                $q->orderBy('created_at');
            }])
            ->get();

        $incomplete = $tasks->where('status', 'incomplete');
        $done       = $tasks->where('status', 'done');
        $archived   = $tasks->where('status', 'archived');

        $lines = [];
        $lines[] = '# ' . $project->name;

        if ($project->description) {
            $lines[] = '';
            $lines[] = $project->description;
        }

        $renderGroup = function($group) use (&$lines) {
            foreach ($group as $task) {
                $lines[] = '* ' . $task->name;
                foreach ($task->children as $child) {
                    $lines[] = '    * ' . $child->name;
                }
            }
        };

        if ($incomplete->isNotEmpty()) {
            $lines[] = '';
            $lines[] = '## Incomplete';
            $renderGroup($incomplete);
        }

        if ($done->isNotEmpty()) {
            $lines[] = '';
            $lines[] = '## Done';
            $renderGroup($done);
        }

        if ($archived->isNotEmpty()) {
            $lines[] = '';
            $lines[] = '## Archived';
            $renderGroup($archived);
        }

        $content  = implode("\n", $lines) . "\n";
        $filename = 'project_' . preg_replace('/[^a-z0-9]+/', '_', strtolower($project->name)) . '_' . now()->format('Y-m-d') . '.md';

        return response($content, 200, [
            'Content-Type'        => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
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
