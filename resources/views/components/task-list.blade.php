@props(['tasks', 'depth' => 0, 'hideDate' => false, 'readOnly' => false])

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

    window.taskFilter = function (projects, tags) {
        return {
            query: '',
            noResults: false,
            mode: 'create',

            // Autocomplete state
            projects: projects || [],
            tags: tags || [],
            showAutocomplete: false,
            autocompleteType: null,
            autocompleteQuery: '',
            autocompleteIndex: 0,

            get filteredProjects() {
                if (!this.autocompleteQuery) return this.projects;
                const q = this.autocompleteQuery.toLowerCase();
                return this.projects.filter(p => p.name.toLowerCase().includes(q));
            },

            get filteredTags() {
                if (!this.autocompleteQuery) return this.tags;
                const q = this.autocompleteQuery.toLowerCase();
                return this.tags.filter(t => t.tag_name.toLowerCase().includes(q));
            },

            handleInput(event) {
                const input = event.target.value;
                const cursorPos = event.target.selectionStart;
                const beforeCursor = input.substring(0, cursorPos);

                const projectMatch = beforeCursor.match(/#(\w*)$/);
                const tagMatch = beforeCursor.match(/@(\w*)$/);

                if (projectMatch && this.projects.length > 0) {
                    this.autocompleteType = 'project';
                    this.autocompleteQuery = projectMatch[1];
                    this.autocompleteIndex = 0;
                    this.showAutocomplete = true;
                } else if (tagMatch && this.tags.length > 0) {
                    this.autocompleteType = 'tag';
                    this.autocompleteQuery = tagMatch[1];
                    this.autocompleteIndex = 0;
                    this.showAutocomplete = true;
                } else {
                    this.showAutocomplete = false;
                }
            },

            handleKeydown(event) {
                if (!this.showAutocomplete) return;
                const list = this.autocompleteType === 'project' ? this.filteredProjects : this.filteredTags;
                const maxIndex = Math.max(0, list.length - 1);

                if (event.key === 'ArrowDown') {
                    event.preventDefault();
                    this.autocompleteIndex = Math.min(this.autocompleteIndex + 1, maxIndex);
                } else if (event.key === 'ArrowUp') {
                    event.preventDefault();
                    this.autocompleteIndex = Math.max(this.autocompleteIndex - 1, 0);
                } else if (event.key === 'Enter') {
                    event.preventDefault();
                    const item = list[this.autocompleteIndex];
                    if (item) this.selectAutocomplete(this.autocompleteType === 'project' ? item.name : item.tag_name);
                } else if (event.key === 'Escape') {
                    event.preventDefault();
                    this.showAutocomplete = false;
                }
            },

            selectAutocomplete(name) {
                const inputEl = this.$refs.createInput;
                if (!inputEl) return;
                const cursorPos = inputEl.selectionStart;
                const beforeCursor = inputEl.value.substring(0, cursorPos);
                const afterCursor = inputEl.value.substring(cursorPos);
                const slug = name.toLowerCase().replace(/[^a-z0-9-]/g, '');
                const newBefore = this.autocompleteType === 'project'
                    ? beforeCursor.replace(/#\w*$/, '#' + slug + ' ')
                    : beforeCursor.replace(/@\w*$/, '@' + slug + ' ');
                inputEl.value = newBefore + afterCursor;
                this.showAutocomplete = false;
                this.$nextTick(() => {
                    inputEl.focus();
                    inputEl.setSelectionRange(newBefore.length, newBefore.length);
                });
            },

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
            switchToFilter() {
                this.mode = 'filter';
                this.showAutocomplete = false;
                this.$nextTick(() => this.$refs.filterInput && this.$refs.filterInput.focus());
            },
            switchToCreate() {
                this.mode = 'create';
                if (this.query) {
                    this.query = '';
                    this.filterTasks();
                }
                this.$nextTick(() => this.$refs.createInput && this.$refs.createInput.focus());
            },
            clearFilter() {
                if (this.query) {
                    this.query = '';
                    this.filterTasks();
                }
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
        <div class="bg-[#202020] p-4 rounded-lg shadow hover:shadow-md transition border border-gray-700"
             data-filterable
             data-task-name="{{ strtolower($task->name) }}"
             data-project="{{ strtolower($task->project?->name ?? '') }}"
             data-tags="{{ strtolower($task->tags->pluck('tag_name')->join('|')) }}"
             style="margin-left: {{ $marginLeft }}px;">
            <div class="flex items-start gap-4">
                <!-- Complete Circle -->
                @if($task->status === 'done')
                    <div class="mt-1 w-6 h-6 rounded-full bg-green-600 flex-shrink-0" title="Completed"></div>
                @elseif($readOnly)
                    <div class="mt-1 w-6 h-6 rounded-full border-2 border-gray-700 flex-shrink-0" title="Project is inactive"></div>
                @else
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
                @endif

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
                        @if($hideDate)
                            @if($task->time)
                                <span class="text-gray-400">{{ \Carbon\Carbon::parse($task->time)->format('g:i A') }}</span>
                            @endif
                        @elseif($task->date)
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
                        @if($task->description)
                            <span class="flex items-center gap-1 text-gray-500" title="Has description">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h10"></path>
                                </svg>
                            </span>
                        @endif
                        @if($task->comments->count() > 0)
                            <span class="flex items-center gap-1 text-gray-500" title="{{ $task->comments->count() }} comment(s)">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                                </svg>
                                {{ $task->comments->count() }}
                            </span>
                        @endif
                        @if($task->attachments->count() > 0)
                            <span class="flex items-center gap-1 text-gray-500" title="{{ $task->attachments->count() }} attachment(s)">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                                </svg>
                            </span>
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

                <!-- Assignee Avatars -->
                @if($task->assignees->count() > 0)
                    <div class="flex-shrink-0 flex space-x-1">
                        @php
                            $avatarColors = ['bg-blue-500', 'bg-green-500', 'bg-yellow-500', 'bg-purple-500', 'bg-pink-500', 'bg-indigo-500', 'bg-red-500', 'bg-teal-500'];
                        @endphp
                        @foreach($task->assignees->take(3) as $assignee)
                            @if($assignee->profile_image)
                                <img src="{{ route('profile.image.show', $assignee) }}"
                                     alt="{{ $assignee->name }}"
                                     title="{{ $assignee->name }}"
                                     class="w-8 h-8 rounded-full object-cover shadow-sm">
                            @else
                                <div class="w-8 h-8 rounded-full {{ $avatarColors[$assignee->id % count($avatarColors)] }} flex items-center justify-center text-xs font-bold text-white shadow-sm"
                                     title="{{ $assignee->name }}">
                                    {{ strtoupper(substr($assignee->name, 0, 1)) }}
                                </div>
                            @endif
                        @endforeach
                        @if($task->assignees->count() > 3)
                            <div class="w-8 h-8 rounded-full bg-gray-600 flex items-center justify-center text-xs font-medium text-gray-300 shadow-sm"
                                 title="{{ $task->assignees->count() - 3 }} more">
                                +{{ $task->assignees->count() - 3 }}
                            </div>
                        @endif
                    </div>
                @endif

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
            <x-task-list :tasks="$task->children" :depth="$depth + 1" :hide-date="$hideDate" :read-only="$readOnly" />
        @endif
    @empty
        <div class="bg-[#202020] p-8 rounded-lg text-center text-gray-400 border border-gray-700">
            No tasks found.
        </div>
    @endforelse
</div>
