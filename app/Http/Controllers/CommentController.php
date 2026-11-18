<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\StoresAttachments;
use App\Models\Comment;
use App\Models\CommentAttachment;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class CommentController extends Controller
{
    use StoresAttachments;
    public function store(Request $request, Task $task)
    {
        $isCreator = $task->creator_id === Auth::id();
        $isAssignee = $task->assignees->contains('id', Auth::id());

        if (!$isCreator && !$isAssignee) {
            abort(403, 'You do not have access to comment on this task.');
        }

        $task->loadMissing('project');
        if ($task->project && in_array($task->project->status, ['done', 'archived'])) {
            abort(403, 'Tasks in inactive projects cannot be modified.');
        }

        // PHP silently rejects files that exceed upload_max_filesize and sets an
        // error code on the upload rather than passing the file to Laravel.
        // Catch that here so the user gets a readable message instead of
        // a confusing validation error.
        if (isset($_FILES['attachments'])) {
            foreach ((array) $_FILES['attachments']['error'] as $err) {
                if (in_array($err, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE])) {
                    $message = 'One or more files are too large. The maximum file size is ' . ini_get('upload_max_filesize') . '.';

                    if ($request->expectsJson()) {
                        return response()->json(['message' => $message, 'errors' => ['attachments' => [$message]]], 422);
                    }

                    return redirect()->back()
                        ->withErrors(['attachments' => $message])
                        ->withInput($request->except('attachments'));
                }
            }
        }

        $maxFileSizeLabel = config('taskfiend.max_file_size');
        $maxFileSizeKb = (int) $maxFileSizeLabel * 1024;

        $longTextMax = (int) config('taskfiend.long_text_max_chars');
        $validated = $request->validate([
            'comment' => "required|string|max:{$longTextMax}",
            'attachments' => ['nullable', 'array'],
            'attachments.*' => [
                'file',
                "max:{$maxFileSizeKb}",
                'mimetypes:' . self::allowedMimetypes(),
            ],
        ], [
            'attachments.*.max' => "File size must not exceed {$maxFileSizeLabel}.",
            'attachments.*.mimetypes' => self::allowedMimetypesMessage(),
        ]);

        $comment = $task->comments()->create([
            'user_id' => Auth::id(),
            'comment' => $validated['comment'],
        ]);

        foreach ($request->file('attachments', []) as $file) {
            [$path, $fileSize, $mimeType] = $this->storeScaled($file, 'comment_attachments');

            $comment->attachments()->create([
                'file_path' => $path,
                'original_filename' => basename($file->getClientOriginalName()),
                'mime_type' => $mimeType,
                'file_size' => $fileSize,
            ]);
        }

        $task->changeLogs()->create([
            'date' => now(),
            'user_id' => Auth::id(),
            'entity_type' => 'tasks',
            'entity_id' => $task->id,
            'description' => 'added a comment',
        ]);

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('tasks.show', $task)
            ->with('success', 'Comment added successfully.');
    }

    public function destroy(Task $task, Comment $comment)
    {
        if ($comment->task_id !== $task->id) {
            abort(404);
        }

        if ($task->creator_id !== Auth::id()) {
            abort(403, 'Only the task creator can delete comments.');
        }

        $task->loadMissing('project');
        if ($task->project && in_array($task->project->status, ['done', 'archived'])) {
            abort(403, 'Tasks in inactive projects cannot be modified.');
        }

        foreach ($comment->attachments as $attachment) {
            Storage::disk('private')->delete($attachment->file_path);
        }

        $comment->delete();

        $task->changeLogs()->create([
            'date' => now(),
            'user_id' => Auth::id(),
            'entity_type' => 'tasks',
            'entity_id' => $task->id,
            'description' => 'deleted a comment',
        ]);

        return redirect()->route('tasks.show', $task)
            ->with('success', 'Comment deleted successfully.');
    }

    public function download(Task $task, Comment $comment, CommentAttachment $attachment)
    {
        $this->authorizeAttachmentAccess($task, $comment, $attachment);

        return Storage::disk('private')->download(
            $attachment->file_path,
            $attachment->original_filename
        );
    }

    public function view(Task $task, Comment $comment, CommentAttachment $attachment)
    {
        $this->authorizeAttachmentAccess($task, $comment, $attachment);

        return response(Storage::disk('private')->get($attachment->file_path), 200)
            ->header('Content-Type', $attachment->mime_type)
            ->header('Content-Disposition', 'inline; filename="' . addslashes($attachment->original_filename) . '"');
    }

    private function authorizeAttachmentAccess(Task $task, Comment $comment, CommentAttachment $attachment): void
    {
        if ($comment->task_id !== $task->id || $attachment->comment_id !== $comment->id) {
            abort(404);
        }

        $isCreator = $task->creator_id === Auth::id();
        $isAssignee = $task->assignees->contains('id', Auth::id());

        if (!$isCreator && !$isAssignee) {
            abort(403, 'You do not have access to this comment attachment.');
        }

        if (!Storage::disk('private')->exists($attachment->file_path)) {
            abort(404, 'Attachment not found.');
        }
    }
}
