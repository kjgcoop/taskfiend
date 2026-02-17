<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-100 leading-tight">
                {{ $task->name }}
            </h2>
        </div>
    </x-slot>

    <!-- Breadcrumb for parent hierarchy -->
    @if($task->parent_id)
    <div class="py-4 bg-black">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <nav class="flex items-center space-x-2 text-sm text-gray-400">
                @foreach($task->getAllAncestors()->reverse() as $ancestor)
                    <a href="{{ route('tasks.show', $ancestor) }}"
                       class="hover:text-gray-100 transition">
                        {{ $ancestor->name }}
                    </a>
                    <span class="text-gray-600">/</span>
                @endforeach
                <span class="text-gray-200 font-semibold">{{ $task->name }}</span>
            </nav>
        </div>
    </div>
    @endif

    <div class="py-12" x-data="taskEditor({{ $task->id }})" x-init="console.log('Alpine initialized', editing, fields)">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <!-- Task Details -->
            <div class="bg-gray-800 border border-gray-700 overflow-hidden shadow-sm sm:rounded-lg p-6">
                <!-- Task Name -->
                <div class="mb-4">
                    <span class="text-sm font-medium text-gray-500">Task Name</span>
                    <div @click="startEdit('name')" x-show="!editing.name" class="mt-1 cursor-pointer hover:bg-gray-700 p-2 rounded">
                        <p class="text-lg font-semibold text-gray-100">{{ $task->name }}</p>
                    </div>
                    <div x-show="editing.name" class="mt-1">
                        <input type="text" x-model="fields.name"
                               @keydown.enter="saveField('name')"
                               @keydown.escape="cancelEdit('name')"
                               class="w-full rounded-md bg-gray-700 border-gray-600 text-gray-100 placeholder-gray-500 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <div class="flex gap-2 mt-2">
                            <button @click="saveField('name')"
                                    class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                                Save
                            </button>
                            <button @click="cancelEdit('name')"
                                    class="px-3 py-1 bg-gray-700 text-gray-300 text-sm rounded hover:bg-gray-600">
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>

                @if($task->recurrence_pattern && $task->status !== 'done' && $task->status !== 'archived')
                <div class="mb-4 p-3 bg-purple-900 bg-opacity-20 border border-purple-500 rounded-lg">
                    <p class="text-sm text-purple-300">
                        <span class="font-semibold">🔄 Recurring Task</span> ({{ $task->recurrence_pattern }})
                    </p>
                    <p class="text-xs text-purple-400 mt-1">
                        <strong>Completing this instance:</strong> Marking this task as "done" will complete ONLY this instance
                        @if($task->children->count() > 0)
                            (and all {{ $task->children->count() }} subtask(s))
                        @endif
                        and automatically create a new one for the next occurrence.
                    </p>
                    <p class="text-xs text-purple-400 mt-1">
                        <strong>To stop the entire series:</strong> Remove the recurrence pattern below before marking done, or change status to "archived" instead.
                    </p>
                </div>
                @endif

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <!-- Status -->
                    <div>
                        <span class="text-sm font-medium text-gray-500">Status</span>
                        <div @click="startEdit('status')" x-show="!editing.status" class="mt-1 cursor-pointer hover:bg-gray-700 p-2 rounded">
                            <span class="inline-block px-2 py-1 text-xs rounded
                                @if($task->status === 'done') bg-green-100 text-green-800
                                @elseif($task->status === 'archived') bg-gray-100 text-gray-800
                                @else bg-blue-100 text-blue-800 @endif">
                                {{ ucfirst($task->status) }}
                            </span>
                            @if($task->recurrence_pattern && $task->status !== 'done')
                                <span class="ml-2 text-xs text-purple-400">🔄</span>
                            @endif
                        </div>
                        <div x-show="editing.status" class="mt-1">
                            <select x-model="fields.status"
                                    @keydown.enter="saveField('status')"
                                    @keydown.escape="cancelEdit('status')"
                                    class="w-full rounded-md bg-gray-700 border-gray-600 text-gray-100 placeholder-gray-500 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="incomplete">Incomplete</option>
                                <option value="done">Done</option>
                                <option value="archived">Archived</option>
                            </select>
                            <div class="flex gap-2 mt-2">
                                <button @click="saveField('status')"
                                        class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                                    Save
                                </button>
                                <button @click="cancelEdit('status')"
                                        class="px-3 py-1 bg-gray-700 text-gray-300 text-sm rounded hover:bg-gray-600">
                                    Cancel
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Created By (read-only) -->
                    <div>
                        <span class="text-sm font-medium text-gray-500">Created By</span>
                        <p class="mt-1 text-gray-300">{{ $task->creator->name }}</p>
                    </div>

                    <!-- Date -->
                    <div>
                        <span class="text-sm font-medium text-gray-500">Date</span>
                        <div @click="startEdit('date')" x-show="!editing.date" class="mt-1 cursor-pointer hover:bg-gray-700 p-2 rounded min-h-[40px]">
                            @if($task->date)
                                <p class="text-gray-300">{{ \Carbon\Carbon::parse($task->date)->format('l, F j, Y') }}</p>
                            @else
                                <p class="text-gray-400 italic">Click to set date</p>
                            @endif
                        </div>
                        <div x-show="editing.date" class="mt-1">
                            <input type="date" x-model="fields.date"
                                   @keydown.enter="saveField('date')"
                                   @keydown.escape="cancelEdit('date')"
                                   class="w-full rounded-md bg-gray-700 border-gray-600 text-gray-100 placeholder-gray-500 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <div class="flex gap-2 mt-2">
                                <button @click="saveField('date')"
                                        class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                                    Save
                                </button>
                                <button @click="cancelEdit('date')"
                                        class="px-3 py-1 bg-gray-700 text-gray-300 text-sm rounded hover:bg-gray-600">
                                    Cancel
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Time -->
                    <div>
                        <span class="text-sm font-medium text-gray-500">Time</span>
                        <div @click="startEdit('time')" x-show="!editing.time" class="mt-1 cursor-pointer hover:bg-gray-700 p-2 rounded min-h-[40px]">
                            @if($task->time)
                                <p class="text-gray-300">{{ \Carbon\Carbon::parse($task->time)->format('g:i A') }}</p>
                            @else
                                <p class="text-gray-400 italic">Click to set time (optional)</p>
                            @endif
                        </div>
                        <div x-show="editing.time" class="mt-1">
                            <input type="time" x-model="fields.time"
                                   @keydown.enter="saveField('time')"
                                   @keydown.escape="cancelEdit('time')"
                                   class="w-full rounded-md bg-gray-700 border-gray-600 text-gray-100 placeholder-gray-500 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <div class="flex gap-2 mt-2">
                                <button @click="saveField('time')"
                                        class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                                    Save
                                </button>
                                <button @click="cancelEdit('time')"
                                        class="px-3 py-1 bg-gray-700 text-gray-300 text-sm rounded hover:bg-gray-600">
                                    Cancel
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Project -->
                    <div>
                        <span class="text-sm font-medium text-gray-500">Project</span>
                        <div @click="startEdit('project_id')" x-show="!editing.project_id" class="mt-1 cursor-pointer hover:bg-gray-700 p-2 rounded">
                            <p class="text-gray-300">{{ $task->project ? $task->project->name : 'Inbox' }}</p>
                        </div>
                        <div x-show="editing.project_id" class="mt-1">
                            <select x-model="fields.project_id"
                                    @keydown.enter="saveField('project_id')"
                                    @keydown.escape="cancelEdit('project_id')"
                                    class="w-full rounded-md bg-gray-700 border-gray-600 text-gray-100 placeholder-gray-500 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                @foreach($projects as $project)
                                    <option value="{{ $project->id }}">{{ $project->name }}</option>
                                @endforeach
                            </select>
                            <div class="flex gap-2 mt-2">
                                <button @click="saveField('project_id')"
                                        class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                                    Save
                                </button>
                                <button @click="cancelEdit('project_id')"
                                        class="px-3 py-1 bg-gray-700 text-gray-300 text-sm rounded hover:bg-gray-600">
                                    Cancel
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Parent Task -->
                    <div>
                        <span class="text-sm font-medium text-gray-500">Parent Task</span>
                        <div @click="startEdit('parent_id')" x-show="!editing.parent_id" class="mt-1 cursor-pointer hover:bg-gray-700 p-2 rounded">
                            <p class="text-gray-300">
                                @if($task->parent)
                                    {{ $task->parent->name }}
                                @else
                                    None (Top-level task)
                                @endif
                            </p>
                        </div>
                        <div x-show="editing.parent_id" class="mt-1">
                            <select x-model="fields.parent_id"
                                    @keydown.enter="saveField('parent_id')"
                                    @keydown.escape="cancelEdit('parent_id')"
                                    class="w-full rounded-md bg-gray-700 border-gray-600 text-gray-100 placeholder-gray-500 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">No Parent (Top-level)</option>
                                @foreach($availableParents as $parentOption)
                                    <option value="{{ $parentOption->id }}">
                                        {{ str_repeat('→ ', $parentOption->getDepth()) }}{{ $parentOption->name }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="flex gap-2 mt-2">
                                <button @click="saveField('parent_id')"
                                        class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                                    Save
                                </button>
                                <button @click="cancelEdit('parent_id')"
                                        class="px-3 py-1 bg-gray-700 text-gray-300 text-sm rounded hover:bg-gray-600">
                                    Cancel
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Description -->
                <div class="mt-4">
                    <span class="text-sm font-medium text-gray-500">Description</span>
                    <div @click="startEdit('description')" x-show="!editing.description" class="mt-1 cursor-pointer hover:bg-gray-700 p-2 rounded min-h-[40px]">
                        @if($task->description)
                            <p class="text-gray-300">{{ $task->description }}</p>
                        @else
                            <p class="text-gray-400 italic">Click to add description</p>
                        @endif
                    </div>
                    <div x-show="editing.description" class="mt-1">
                        <textarea x-model="fields.description" rows="3"
                                  @keydown.enter.ctrl="saveField('description')"
                                  @keydown.escape="cancelEdit('description')"
                                  class="w-full rounded-md bg-gray-700 border-gray-600 text-gray-100 placeholder-gray-500 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                        <div class="flex gap-2 mt-2">
                            <button @click="saveField('description')"
                                    class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                                Save
                            </button>
                            <button @click="cancelEdit('description')"
                                    class="px-3 py-1 bg-gray-700 text-gray-300 text-sm rounded hover:bg-gray-600">
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Recurrence Pattern -->
                <div class="mt-4">
                    <span class="text-sm font-medium text-gray-500">Recurrence</span>
                    <div @click="startEdit('recurrence_pattern')" x-show="!editing.recurrence_pattern" class="mt-1 cursor-pointer hover:bg-gray-700 p-2 rounded min-h-[40px]">
                        @if($task->recurrence_pattern)
                            <p class="text-purple-400 font-semibold">🔄 {{ $task->recurrence_pattern }}</p>
                            @if($nextDueDate && $task->status !== 'done')
                            <p class="text-xs text-gray-300 mt-1">Due date after the current: {{ $nextDueDate }}</p>
                            @endif
                            @if($task->status !== 'done')
                            <p class="text-xs text-gray-400 mt-1">ℹ️ When marked done, this task will be completed and a new instance will be created for the next occurrence.</p>
                            @endif
                        @else
                            <p class="text-gray-400 italic">Click to set recurrence</p>
                        @endif
                    </div>
                    <div x-show="editing.recurrence_pattern" class="mt-1">
                        <input type="text" x-model="fields.recurrence_pattern"
                               placeholder="e.g., daily, every Monday, weekdays"
                               @keydown.enter="saveField('recurrence_pattern')"
                               @keydown.escape="cancelEdit('recurrence_pattern')"
                               class="w-full rounded-md bg-gray-700 border-gray-600 text-gray-100 placeholder-gray-500 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <div class="flex gap-2 mt-2">
                            <button @click="saveField('recurrence_pattern')"
                                    class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                                Save
                            </button>
                            <button @click="cancelEdit('recurrence_pattern')"
                                    class="px-3 py-1 bg-gray-700 text-gray-300 text-sm rounded hover:bg-gray-600">
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Tags -->
                <div class="mt-4">
                    <span class="text-sm font-medium text-gray-500">Tags</span>
                    <div @click="startEdit('tag_ids')" x-show="!editing.tag_ids" class="mt-1 cursor-pointer hover:bg-gray-700 p-2 rounded min-h-[40px]">
                        @if($task->tags->count() > 0)
                            <div class="flex gap-2 flex-wrap">
                                @foreach($task->tags as $tag)
                                    <span class="inline-block px-2 py-1 text-xs rounded"
                                          style="background-color: {{ $tag->color }}22; color: {{ $tag->color }}">
                                        {{ $tag->tag_name }}
                                    </span>
                                @endforeach
                            </div>
                        @else
                            <p class="text-gray-500 italic">No tags - click to add</p>
                        @endif
                    </div>
                    <div x-show="editing.tag_ids" class="mt-1">
                        <div class="space-y-2 mb-2 max-h-48 overflow-y-auto border border-gray-600 bg-gray-900 rounded p-3">
                            @forelse($tags as $tag)
                                <label class="flex items-center">
                                    <input type="checkbox" value="{{ $tag->id }}" x-model="fields.tag_ids"
                                           class="rounded border-gray-600 bg-gray-700 text-blue-600 focus:ring-blue-500">
                                    <span class="ml-2 text-sm" style="color: {{ $tag->color }}">{{ $tag->tag_name }}</span>
                                </label>
                            @empty
                                <p class="text-sm text-gray-500">No tags available. Create tags first.</p>
                            @endforelse
                        </div>
                        <div class="flex gap-2 mt-2">
                            <button @click="saveField('tag_ids')"
                                    class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                                Save
                            </button>
                            <button @click="cancelEdit('tag_ids')"
                                    class="px-3 py-1 bg-gray-700 text-gray-300 text-sm rounded hover:bg-gray-600">
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Assignees -->
                @if($task->creator_id === Auth::id())
                <div class="mt-4">
                    <span class="text-sm font-medium text-gray-500">Assigned To</span>
                    <div @click="startEdit('assignee_ids')" x-show="!editing.assignee_ids" class="mt-1 cursor-pointer hover:bg-gray-700 p-2 rounded">
                        <div class="space-y-1">
                            @foreach($task->assignees as $assignee)
                                <p class="text-sm text-gray-300">{{ $assignee->name }}</p>
                            @endforeach
                        </div>
                    </div>
                    <div x-show="editing.assignee_ids" class="mt-1">
                        <div class="space-y-2 mb-2 max-h-48 overflow-y-auto border border-gray-600 bg-gray-900 rounded p-3">
                            @foreach($users as $user)
                                <label class="flex items-center">
                                    <input type="checkbox" value="{{ $user->id }}" x-model="fields.assignee_ids"
                                           class="rounded border-gray-600 bg-gray-700 text-blue-600 focus:ring-blue-500">
                                    <span class="ml-2 text-sm text-gray-300">{{ $user->name }} ({{ $user->email }})</span>
                                </label>
                            @endforeach
                        </div>
                        <div class="flex gap-2 mt-2">
                            <button @click="saveField('assignee_ids')"
                                    class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                                Save
                            </button>
                            <button @click="cancelEdit('assignee_ids')"
                                    class="px-3 py-1 bg-gray-700 text-gray-300 text-sm rounded hover:bg-gray-600">
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
                @else
                <div class="mt-4">
                    <span class="text-sm font-medium text-gray-500">Assigned To</span>
                    <div class="mt-1 space-y-1">
                        @foreach($task->assignees as $assignee)
                            <p class="text-sm text-gray-300">{{ $assignee->name }}</p>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>

            <!-- Subtasks -->
            <div class="bg-gray-800 border border-gray-700 overflow-hidden shadow-sm sm:rounded-lg p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-100">
                        Subtasks
                        @if($task->children->count() > 0)
                            <span class="text-sm text-gray-400 font-normal">
                                ({{ $task->incompleteChildren()->count() }} of {{ $task->children->count() }} incomplete)
                            </span>
                        @endif
                    </h3>
                    <a href="{{ route('tasks.create', ['parent_id' => $task->id]) }}"
                       class="inline-flex items-center px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                        + Add Subtask
                    </a>
                </div>

                @if($task->children->count() > 0)
                    <div class="space-y-2">
                        <x-subtask-list :tasks="$task->children" :parent="$task" />
                    </div>
                @else
                    <p class="text-sm text-gray-500 italic">No subtasks yet. Click "Add Subtask" to create one.</p>
                @endif
            </div>

            <!-- Attachments -->
            <div class="bg-gray-800 border border-gray-700 overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-100 mb-4">Attachments</h3>
                @if($task->attachments->count() > 0)
                    <div class="space-y-2">
                        @foreach($task->attachments as $attachment)
                            <div class="flex items-center gap-3 p-2 bg-gray-700 border border-gray-600 rounded">
                                <!-- Thumbnail/Icon -->
                                <div class="flex-shrink-0 w-16 h-16 bg-gray-600 rounded overflow-hidden flex items-center justify-center">
                                    @if(str_starts_with($attachment->mime_type, 'image/'))
                                        <img src="{{ route('attachments.view', [$task, $attachment]) }}"
                                             alt="{{ $attachment->original_filename }}"
                                             class="w-full h-full object-cover">
                                    @else
                                        <!-- File icon placeholder -->
                                        <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                        </svg>
                                    @endif
                                </div>

                                <!-- Filename and Actions -->
                                <div class="flex-1 flex items-center justify-between">
                                    <span class="text-sm text-gray-300">{{ $attachment->original_filename }}</span>
                                    <div class="flex gap-2">
                                        <a href="{{ route('attachments.download', [$task, $attachment]) }}" class="text-sm text-blue-400 hover:underline">
                                            Download
                                        </a>
                                        @if($task->creator_id === Auth::id())
                                            <form method="POST" action="{{ route('attachments.destroy', [$task, $attachment]) }}" class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-sm text-red-600 hover:underline" onclick="return confirm('Are you sure?')">
                                                    Delete
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-gray-500">No attachments yet.</p>
                @endif

                <form method="POST" action="{{ route('attachments.store', $task) }}" enctype="multipart/form-data" class="mt-4">
                    @csrf
                    <div class="flex gap-2">
                        <input type="file" name="attachment" required class="flex-1 text-sm text-gray-300">
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                            Upload
                        </button>
                    </div>
                </form>
            </div>

            <!-- Comments -->
            <div class="bg-gray-800 border border-gray-700 overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-100 mb-4">Comments</h3>
                <div class="space-y-4 mb-6">
                    @forelse($task->comments as $comment)
                        <div class="border-l-2 border-gray-600 pl-4">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-gray-300">{{ $comment->user->name }}</span>
                                <div class="flex items-center gap-2">
                                    <span class="text-xs text-gray-500">{{ $comment->created_at->diffForHumans() }}</span>
                                    @if($task->creator_id === Auth::id())
                                        <form method="POST" action="{{ route('comments.destroy', [$task, $comment]) }}" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs text-red-600 hover:underline" onclick="return confirm('Are you sure?')">
                                                Delete
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                            <p class="mt-1 text-sm text-gray-300">{{ $comment->comment }}</p>
                            @if($comment->file_path)
                                <p class="mt-1 text-xs">
                                    <a href="{{ route('comments.download', [$task, $comment]) }}" class="text-blue-400 hover:text-blue-300 hover:underline">
                                        📎 {{ $comment->original_filename }}
                                    </a>
                                    <span class="text-gray-500 ml-2">({{ number_format($comment->file_size / 1024, 1) }} KB)</span>
                                </p>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">No comments yet.</p>
                    @endforelse
                </div>

                <form method="POST" action="{{ route('comments.store', $task) }}" enctype="multipart/form-data">
                    @csrf
                    <textarea name="comment" rows="3" required
                              class="w-full rounded-md bg-gray-700 border-gray-600 text-gray-100 placeholder-gray-500 shadow-sm focus:border-blue-500 focus:ring-blue-500 mb-2"
                              placeholder="Add a comment..."></textarea>
                    <div class="flex items-center gap-4">
                        <input type="file" name="attachment" class="text-sm text-gray-300">
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                            Post Comment
                        </button>
                    </div>
                </form>
            </div>

            <!-- Change Log -->
            <div class="bg-gray-800 border border-gray-700 overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-100 mb-4">Change Log</h3>
                <div class="space-y-4">
                    <x-change :changes="$task->changeLogs" />
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function taskEditor(taskId) {
            return {
                taskId: taskId,
                editing: {},
                fields: {
                    name: @js($task->name),
                    description: @js($task->description ?? ''),
                    status: @js($task->status),
                    date: @js($task->date ?? ''),
                    time: @js($task->time ?? ''),
                    project_id: @js($task->project_id ?? $inboxProjectId),
                    recurrence_pattern: @js($task->recurrence_pattern ?? ''),
                    tag_ids: @js($task->tags->pluck('id')->toArray()),
                    assignee_ids: @js($task->assignees->pluck('id')->toArray()),
                },
                original: {},

                init() {
                    this.original = JSON.parse(JSON.stringify(this.fields));
                },

                startEdit(field) {
                    this.editing[field] = true;
                },

                cancelEdit(field) {
                    this.editing[field] = false;
                    this.resetField(field);
                },

                resetField(field) {
                    this.fields[field] = JSON.parse(JSON.stringify(this.original[field]));
                },

                async saveField(field) {
                    try {
                        // Check if marking a recurring task as done
                        if (field === 'status' && this.fields.status === 'done' && this.fields.recurrence_pattern) {
                            const confirmed = confirm(
                                '🔄 Recurring Task: Marking as Done\n\n' +
                                'This will complete THIS instance only and create a new task for the next occurrence.\n\n' +
                                'To stop the recurring series instead:\n' +
                                '• Remove the recurrence pattern first, then mark done, OR\n' +
                                '• Change status to "Archived" instead\n\n' +
                                'Continue with marking this instance done?'
                            );
                            if (!confirmed) {
                                this.resetField(field);
                                this.editing[field] = false;
                                return;
                            }
                        }

                        const formData = new FormData();
                        formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);
                        formData.append('field', field);

                        if (Array.isArray(this.fields[field])) {
                            this.fields[field].forEach(value => {
                                formData.append(field + '[]', value);
                            });
                        } else {
                            formData.append('value', this.fields[field]);
                        }

                        const response = await fetch(`/tasks/${this.taskId}/update-field`, {
                            method: 'POST',
                            body: formData,
                        });

                        const data = await response.json();

                        if (data.success) {
                            this.original[field] = JSON.parse(JSON.stringify(this.fields[field]));
                            this.editing[field] = false;

                            // Reload page to show updated data
                            window.location.reload();
                        } else {
                            alert('Error: ' + (data.message || 'Failed to update'));
                            this.resetField(field);
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        alert('An error occurred while saving');
                        this.resetField(field);
                    }
                },
            };
        }
    </script>
    @endpush
</x-app-layout>
