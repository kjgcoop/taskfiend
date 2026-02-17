<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class TaskAttachmentController extends Controller
{
    public function store(Request $request, Task $task)
    {
        $isCreator = $task->creator_id === Auth::id();
        $isAssignee = $task->assignees->contains('id', Auth::id());

        if (!$isCreator && !$isAssignee) {
            abort(403, 'You do not have access to add attachments to this task.');
        }

        $validated = $request->validate([
            'attachment' => 'required|file|max:22528', // 22MB max (matches PHP upload_max_filesize)
        ], [
            'attachment.max' => 'File size must not exceed 22MB.',
        ]);

        $file = $request->file('attachment');
        $path = $file->store('task_attachments', 'private');

        $attachment = TaskAttachment::create([
            'user_id' => Auth::id(),
            'task_id' => $task->id,
            'file_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
        ]);

        $task->changeLogs()->create([
            'date' => now(),
            'user_id' => Auth::id(),
            'entity_type' => 'tasks',
            'entity_id' => $task->id,
            'description' => 'added an attachment',
        ]);

        return redirect()->route('tasks.show', $task)
            ->with('success', 'Attachment added successfully.');
    }

    public function destroy(Task $task, TaskAttachment $attachment)
    {
        if ($attachment->task_id !== $task->id) {
            abort(404);
        }

        if ($task->creator_id !== Auth::id()) {
            abort(403, 'Only the task creator can delete attachments.');
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
