<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\StoresAttachments;
use App\Models\Comment;
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
        // a confusing "field is required" validation error.
        if (isset($_FILES['attachment']) && in_array($_FILES['attachment']['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE])) {
            return redirect()->back()
                ->withErrors(['attachment' => 'The uploaded file is too large. The maximum file size is ' . ini_get('upload_max_filesize') . '.'])
                ->withInput($request->except('attachment'));
        }

        $maxFileSizeLabel = config('taskfiend.max_file_size');
        $maxFileSizeKb = (int) $maxFileSizeLabel * 1024;

        $longTextMax = (int) config('taskfiend.long_text_max_chars');
        $validated = $request->validate([
            'comment' => "required|string|max:{$longTextMax}",
            'attachment' => [
                'nullable',
                'file',
                "max:{$maxFileSizeKb}",
                'mimetypes:' . self::allowedMimetypes(),
            ],
        ], [
            'attachment.max' => "File size must not exceed {$maxFileSizeLabel}.",
            'attachment.mimetypes' => self::allowedMimetypesMessage(),
        ]);

        $commentData = [
            'user_id' => Auth::id(),
            'comment' => $validated['comment'],
        ];

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            [$path, $fileSize, $mimeType] = $this->storeScaled($file, 'comment_attachments');

            $commentData['file_path'] = $path;
            $commentData['original_filename'] = basename($file->getClientOriginalName());
            $commentData['mime_type'] = $mimeType;
            $commentData['file_size'] = $fileSize;
        }

        $comment = $task->comments()->create($commentData);

        $task->changeLogs()->create([
            'date' => now(),
            'user_id' => Auth::id(),
            'entity_type' => 'tasks',
            'entity_id' => $task->id,
            'description' => 'added a comment',
        ]);

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

        if ($comment->file_path) {
            Storage::disk('private')->delete($comment->file_path);
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

    public function download(Task $task, Comment $comment)
    {
        if ($comment->task_id !== $task->id) {
            abort(404);
        }

        $isCreator = $task->creator_id === Auth::id();
        $isAssignee = $task->assignees->contains('id', Auth::id());

        if (!$isCreator && !$isAssignee) {
            abort(403, 'You do not have access to this comment attachment.');
        }

        if (!$comment->file_path || !Storage::disk('private')->exists($comment->file_path)) {
            abort(404, 'Attachment not found.');
        }

        return Storage::disk('private')->download(
            $comment->file_path,
            $comment->original_filename
        );
    }

    public function view(Task $task, Comment $comment)
    {
        if ($comment->task_id !== $task->id) {
            abort(404);
        }

        $isCreator = $task->creator_id === Auth::id();
        $isAssignee = $task->assignees->contains('id', Auth::id());

        if (!$isCreator && !$isAssignee) {
            abort(403, 'You do not have access to this comment attachment.');
        }

        if (!$comment->file_path || !Storage::disk('private')->exists($comment->file_path)) {
            abort(404, 'Attachment not found.');
        }

        return response(Storage::disk('private')->get($comment->file_path), 200)
            ->header('Content-Type', $comment->mime_type)
            ->header('Content-Disposition', 'inline; filename="' . addslashes($comment->original_filename) . '"');
    }

}
