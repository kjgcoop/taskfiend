@props(['tasks', 'depth' => 0])

@pushOnce('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.store('taskCount', {
            total: 0,
            visible: 0,
            filtered: false,
            ready: false
        });
    });

    window.taskFilter = function () {
        return {
            query: '',
            noResults: false,
            init() {
                this.$nextTick(() => {
                    const container = this.$refs.taskContainer;
                    if (container) {
                        const total = container.querySelectorAll('[data-filterable]').length;
                        Alpine.store('taskCount').total = total;
                        Alpine.store('taskCount').visible = total;
                        Alpine.store('taskCount').filtered = false;
                        Alpine.store('taskCount').ready = true;
                    }
                });
            },
            filterTasks() {
                const container = this.$refs.taskContainer;
                const tasks = container.querySelectorAll('[data-filterable]');
                const query = this.query.trim().toLowerCase();

                if (!query) {
                    tasks.forEach(el => el.style.display = '');
                    this.noResults = false;
                    Alpine.store('taskCount').visible = tasks.length;
                    Alpine.store('taskCount').filtered = false;
                    return;
                }

                const tokens = query.split(/\s+/).filter(t => t.length > 0);
                const projectFilters = [];
                const tagFilters = [];
                const nameFilters = [];

                tokens.forEach(token => {
                    if (token.startsWith('#') && token.length > 1) {
                        projectFilters.push(token.substring(1));
                    } else if (token.startsWith('@') && token.length > 1) {
                        tagFilters.push(token.substring(1));
                    } else {
                        nameFilters.push(token);
                    }
                });

                let visibleCount = 0;

                tasks.forEach(el => {
                    const taskName = el.dataset.taskName || '';
                    const projectName = el.dataset.project || '';
                    const tagList = el.dataset.tags ? el.dataset.tags.split('|') : [];

                    let matches = true;

                    for (const filter of nameFilters) {
                        if (!taskName.includes(filter)) {
                            matches = false;
                            break;
                        }
                    }

                    if (matches) {
                        for (const filter of projectFilters) {
                            if (!projectName.includes(filter)) {
                                matches = false;
                                break;
                            }
                        }
                    }

                    if (matches) {
                        for (const filter of tagFilters) {
                            if (!tagList.some(tag => tag.includes(filter))) {
                                matches = false;
                                break;
                            }
                        }
                    }

                    el.style.display = matches ? '' : 'none';
                    if (matches) visibleCount++;
                });

                this.noResults = visibleCount === 0 && tasks.length > 0;
                Alpine.store('taskCount').visible = visibleCount;
                Alpine.store('taskCount').filtered = true;
            }
        }
    };
</script>
@endPushOnce

<div class="space-y-2">
    @forelse($tasks as $task)
        @php
            // Find first image attachment from task or comments
            $imageAttachment = null;
            $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];

            // Check task attachments first
            foreach($task->attachments as $attachment) {
                $ext = strtolower(pathinfo($attachment->file_path, PATHINFO_EXTENSION));
                if (in_array($ext, $imageExtensions)) {
                    $imageAttachment = $attachment;
                    break;
                }
            }

            // If no task attachment image, check comment attachments
            if (!$imageAttachment) {
                foreach($task->comments as $comment) {
                    if ($comment->attachment_path) {
                        $ext = strtolower(pathinfo($comment->attachment_path, PATHINFO_EXTENSION));
                        if (in_array($ext, $imageExtensions)) {
                            $imageAttachment = $comment;
                            break;
                        }
                    }
                }
            }

            $marginLeft = $depth * 24; // 24px per level
        @endphp
        <div class="bg-gray-800 p-4 rounded-lg shadow hover:shadow-md transition border border-gray-700"
             data-filterable
             data-task-name="{{ strtolower($task->name) }}"
             data-project="{{ strtolower($task->project?->name ?? '') }}"
             data-tags="{{ strtolower($task->tags->pluck('tag_name')->join('|')) }}"
             style="margin-left: {{ $marginLeft }}px;">
            <div class="flex items-start gap-4">
                <!-- Complete Circle -->
                <form method="POST" action="{{ route('tasks.update', $task) }}" onclick="event.stopPropagation()">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="status" value="done">
                    <input type="hidden" name="name" value="{{ $task->name }}">
                    <input type="hidden" name="description" value="{{ $task->description }}">
                    <input type="hidden" name="date" value="{{ $task->date }}">
                    <input type="hidden" name="time" value="{{ $task->time }}">
                    <input type="hidden" name="project_id" value="{{ $task->project_id }}">
                    <input type="hidden" name="parent_id" value="{{ $task->parent_id }}">
                    <input type="hidden" name="recurrence_pattern" value="{{ $task->recurrence_pattern }}">
                    @foreach($task->tags as $tag)
                        <input type="hidden" name="tag_ids[]" value="{{ $tag->id }}">
                    @endforeach
                    @foreach($task->assignees as $assignee)
                        <input type="hidden" name="assignee_ids[]" value="{{ $assignee->id }}">
                    @endforeach
                    <input type="hidden" name="quick_complete" value="1">
                    @php
                        $buttonClass = 'mt-1 w-6 h-6 rounded-full border-2 transition flex-shrink-0';
                        $titleText = 'Mark as done';

                        if ($task->recurrence_pattern && $task->children->count() > 0) {
                            $buttonClass .= ' border-purple-400 hover:border-purple-500';
                            $titleText = 'Complete & create next with ' . $task->children->count() . ' subtask(s) (' . $task->recurrence_pattern . ')';
                        } elseif ($task->recurrence_pattern) {
                            $buttonClass .= ' border-purple-400 hover:border-purple-500';
                            $titleText = 'Complete & create next (' . $task->recurrence_pattern . ')';
                        } elseif ($task->children->count() > 0) {
                            $buttonClass .= ' border-blue-400 hover:border-blue-500';
                            $titleText = 'Complete with ' . $task->children->count() . ' subtask(s)';
                        } else {
                            $buttonClass .= ' border-gray-400 hover:border-green-400';
                        }

                        $buttonClass .= ' hover:bg-green-400 hover:bg-opacity-20';
                    @endphp
                    <button type="submit"
                            class="{{ $buttonClass }}"
                            title="{{ $titleText }}">
                    </button>
                </form>

                <!-- Task Content -->
                <div class="flex-1 cursor-pointer" onclick="window.location='{{ route('tasks.show', $task) }}'">
                    <!-- Show parent context if exists -->
                    @if($task->parent)
                        <div class="text-xs text-gray-500 mb-1">
                            <span class="text-gray-600">↳ Subtask of:</span>
                            <a href="{{ route('tasks.show', $task->parent) }}"
                               class="text-blue-400 hover:underline"
                               onclick="event.stopPropagation()">
                                {{ $task->parent->name }}
                            </a>
                        </div>
                    @endif

                    <h3 class="font-semibold text-gray-100">
                        {{ $task->name }}
                        @if($task->children->count() > 0)
                            <span class="text-xs text-gray-500 font-normal">
                                ({{ $task->incompleteChildren()->count() }}/{{ $task->children->count() }} subtasks)
                            </span>
                        @endif
                    </h3>
                    @if($task->description)
                        <p class="text-sm text-gray-400 mt-1">{{ Str::limit($task->description, 100) }}</p>
                    @endif
                    <div class="flex items-center gap-3 mt-2 text-xs text-gray-500">
                        @if($task->date)
                            <span>
                                {{ \Carbon\Carbon::parse($task->date)->format('l, F j, Y') }}
                                @if($task->time)
                                    <span class="text-gray-400">{{ \Carbon\Carbon::parse($task->time)->format('g:i A') }}</span>
                                @endif
                            </span>
                        @endif
                        @if($task->project)
                            <span class="text-blue-400">{{ $task->project->name }}</span>
                        @endif
                        @if($task->recurrence_pattern)
                            <span class="text-purple-400">{{ $task->recurrence_pattern }}</span>
                        @endif
                    </div>
                    @if($task->tags->count() > 0)
                        <div class="flex gap-1 mt-2">
                            @foreach($task->tags as $tag)
                                <span class="inline-block px-2 py-1 text-xs rounded"
                                      style="background-color: {{ $tag->color }}22; color: {{ $tag->color }}">
                                    {{ $tag->tag_name }}
                                </span>
                            @endforeach
                        </div>
                    @endif
                </div>

                <!-- Image Thumbnail -->
                @if($imageAttachment)
                    <div class="flex-shrink-0">
                        @if($imageAttachment instanceof \App\Models\TaskAttachment)
                            <img src="{{ route('attachments.download', [$task, $imageAttachment]) }}"
                                 alt="Attachment"
                                 class="w-16 h-16 object-cover rounded border border-gray-600">
                        @else
                            <img src="{{ Storage::url($imageAttachment->attachment_path) }}"
                                 alt="Comment attachment"
                                 class="w-16 h-16 object-cover rounded border border-gray-600">
                        @endif
                    </div>
                @endif
            </div>
        </div>

        <!-- Recursively render subtasks -->
        @if($task->children->count() > 0)
            <x-task-list :tasks="$task->children" :depth="$depth + 1" />
        @endif
    @empty
        <div class="bg-gray-800 p-8 rounded-lg text-center text-gray-400 border border-gray-700">
            No tasks found.
        </div>
    @endforelse
</div>
