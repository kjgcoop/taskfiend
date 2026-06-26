<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\StoresAttachments;
use App\Models\Task;
use App\Models\TaskAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class TaskAttachmentController extends Controller
{
    use StoresAttachments;
    public function store(Request $request, Task $task)
    {
        $isCreator = $task->creator_id === Auth::id();
        $isAssignee = $task->assignees->contains('id', Auth::id());

        if (!$isCreator && !$isAssignee) {
            abort(403, 'You do not have access to add attachments to this task.');
        }

        $task->loadMissing('project');
        if ($task->project && in_array($task->project->status, ['done', 'archived'])) {
            abort(403, 'Tasks in inactive projects cannot be modified.');
        }

        // PHP silently rejects files that exceed upload_max_filesize and sets an
        // error code on the upload rather than passing the file to Laravel.
        // Catch that here so the user gets a readable message instead of
        // a confusing "field is required" validation error.
        if (isset($_FILES['attachments'])) {
            $errors = (array) $_FILES['attachments']['error'];
            foreach ($errors as $err) {
                if (in_array($err, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE])) {
                    return redirect()->back()
                        ->withErrors(['attachments' => 'One or more files are too large. The maximum file size is ' . ini_get('upload_max_filesize') . '.']);
                }
            }
        }

        $maxFileSizeLabel = env('MAX_FILE_SIZE', '22M');
        $maxFileSizeKb = (int) $maxFileSizeLabel * 1024;

        $request->validate([
            'attachments' => ['required', 'array'],
            'attachments.*' => [
                'file',
                "max:{$maxFileSizeKb}",
                'mimetypes:' . self::allowedMimetypes(),
            ],
        ], [
            'attachments.required' => 'Please select at least one file.',
            'attachments.*.max' => "File size must not exceed {$maxFileSizeLabel}.",
            'attachments.*.mimetypes' => self::allowedMimetypesMessage(),
        ]);

        $count = 0;
        foreach ($request->file('attachments') as $file) {
            [$path, $fileSize, $mimeType] = $this->storeScaled($file, 'task_attachments');

            $task->attachments()->create([
                'user_id' => Auth::id(),
                'file_path' => $path,
                'original_filename' => basename($file->getClientOriginalName()),
                'mime_type' => $mimeType,
                'file_size' => $fileSize,
            ]);

            $count++;
        }

        $task->changeLogs()->create([
            'date' => now(),
            'user_id' => Auth::id(),
            'entity_type' => 'tasks',
            'entity_id' => $task->id,
            'description' => $count === 1 ? 'added an attachment' : "added {$count} attachments",
        ]);

        return redirect()->route('tasks.show', $task)
            ->with('success', $count === 1 ? 'Attachment added successfully.' : "{$count} attachments added successfully.");
    }

    public function destroy(Task $task, TaskAttachment $attachment)
    {
        if ($attachment->task_id !== $task->id) {
            abort(404);
        }

        if ($task->creator_id !== Auth::id()) {
            abort(403, 'Only the task creator can delete attachments.');
        }

        $task->loadMissing('project');
        if ($task->project && in_array($task->project->status, ['done', 'archived'])) {
            abort(403, 'Tasks in inactive projects cannot be modified.');
        }

        Storage::disk('private')->delete($attachment->file_path);

        $attachment->delete();

        $task->changeLogs()->create([
            'date' => now(),
            'user_id' => Auth::id(),
            'entity_type' => 'tasks',
            'entity_id' => $task->id,
            'description' => 'deleted an attachment',
        ]);

        return redirect()->route('tasks.show', $task)
            ->with('success', 'Attachment deleted successfully.');
    }

    public function download(Task $task, TaskAttachment $attachment)
    {
        if ($attachment->task_id !== $task->id) {
            abort(404);
        }

        $isCreator = $task->creator_id === Auth::id();
        $isAssignee = $task->assignees->contains('id', Auth::id());

        if (!$isCreator && !$isAssignee) {
            abort(403, 'You do not have access to this attachment.');
        }

        return Storage::disk('private')->download(
            $attachment->file_path,
            $attachment->original_filename
        );
    }

    public function view(Task $task, TaskAttachment $attachment)
    {
        if ($attachment->task_id !== $task->id) {
            abort(404);
        }

        $isCreator = $task->creator_id === Auth::id();
        $isAssignee = $task->assignees->contains('id', Auth::id());

        if (!$isCreator && !$isAssignee) {
            abort(403, 'You do not have access to this attachment.');
        }

        return Storage::disk('private')->response(
            $attachment->file_path,
            $attachment->original_filename,
            [
                'Content-Type' => $attachment->mime_type,
            ]
        );
    }

}
