<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class CommentController extends Controller
{
    public function store(Request $request, Task $task)
    {
        $isCreator = $task->creator_id === Auth::id();
        $isAssignee = $task->assignees->contains('id', Auth::id());

        if (!$isCreator && !$isAssignee) {
            abort(403, 'You do not have access to comment on this task.');
        }

        $validated = $request->validate([
            'comment' => 'required|string',
            'attachment' => 'nullable|file|max:22528', // 22MB max (matches PHP upload_max_filesize)
        ], [
            'attachment.max' => 'File size must not exceed 22MB.',
        ]);

        $commentData = [
            'user_id' => Auth::id(),
            'task_id' => $task->id,
            'comment' => $validated['comment'],
        ];

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $path = $file->store('comment_attachments', 'private');

            $commentData['file_path'] = $path;
            $commentData['original_filename'] = $file->getClientOriginalName();
            $commentData['mime_type'] = $file->getMimeType();
            $commentData['file_size'] = $file->getSize();
        }

        $comment = Comment::create($commentData);

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
}
