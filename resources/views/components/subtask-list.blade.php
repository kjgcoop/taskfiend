@props(['tasks', 'parent'])

@pushOnce('scripts')
<script>
    window.listQuickComplete = window.listQuickComplete || function () {
        return {
            done: false,
            loading: false,
            async submit() {
                this.loading = true;
                const form = this.$el;
                try {
                    const res = await fetch(form.action, {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        body: new FormData(form),
                    });
                    if (res.ok) {
                        this.done = true;
                        await new Promise(r => setTimeout(r, 400));
                        const group = form.closest('[data-task-group]');
                        if (group) {
                            group.style.transition = 'opacity 0.3s';
                            group.style.opacity = '0';
                            setTimeout(() => group.style.display = 'none', 300);
                        }
                    }
                } catch {
                    form.submit();
                } finally {
                    this.loading = false;
                }
            }
        };
    };
</script>
@endPushOnce

<div class="space-y-2">
    @foreach($tasks as $task)
        <div data-task-group x-data="{ subtasksOpen: true }">
        <div class="bg-gray-700 border border-gray-600 p-3 rounded-lg hover:bg-gray-650 transition">
            <div class="flex items-start gap-3">
                <!-- Status Indicator -->
                <div class="flex-shrink-0 mt-1">
                    @if($task->status === 'done')
                        <span class="inline-block w-5 h-5 rounded-full bg-green-500 flex items-center justify-center">
                            <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                            </svg>
                        </span>
                    @elseif($task->status === 'archived')
                        <span class="inline-block w-5 h-5 rounded-full bg-gray-500 flex-shrink-0" title="Archived"></span>
                    @else
                        <form x-data="listQuickComplete()" @submit.prevent="submit()"
                              method="POST" action="{{ route('tasks.update', $task) }}" class="inline">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="status" value="done">
                            <input type="hidden" name="name" value="{{ $task->name }}">
                            <input type="hidden" name="description" value="{{ $task->description }}">
                            <input type="hidden" name="date" value="{{ $task->date }}">
                            <input type="hidden" name="time" value="{{ $task->time }}">
                            <input type="hidden" name="project_id" value="{{ $task->project_id }}">
                            <input type="hidden" name="parent_id" value="{{ $task->parent_id }}">
                            @foreach($task->tags as $tag)
                                <input type="hidden" name="tag_ids[]" value="{{ $tag->id }}">
                            @endforeach
                            @foreach($task->assignees as $assignee)
                                <input type="hidden" name="assignee_ids[]" value="{{ $assignee->id }}">
                            @endforeach
                            <input type="hidden" name="quick_complete" value="1">
                            <button x-show="!done" type="submit"
                                    :disabled="loading" :class="loading ? 'opacity-40 cursor-wait' : ''"
                                    class="w-5 h-5 rounded-full border-2 border-gray-400 hover:border-green-400 hover:bg-green-400 hover:bg-opacity-20 transition"
                                    title="Mark as done">
                            </button>
                            <span x-show="done" class="inline-block w-5 h-5 rounded-full bg-green-500" style="display:none"></span>
                        </form>
                    @endif
                </div>

                <!-- Task Info -->
                <div class="flex-1 min-w-0">
                    <div class="flex items-start justify-between gap-2">
                        <a href="{{ route('tasks.show', $task) }}" class="block hover:text-gray-100 transition min-w-0">
                            <h4 class="font-medium truncate {{ $task->status === 'archived' ? 'line-through text-gray-500' : 'text-gray-200' }}">{{ $task->name }}</h4>
                        </a>
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
                                             class="w-6 h-6 rounded-full object-cover shadow-sm">
                                    @else
                                        <div class="w-6 h-6 rounded-full {{ $avatarColors[$assignee->id % count($avatarColors)] }} flex items-center justify-center text-[10px] font-bold text-white shadow-sm"
                                             title="{{ $assignee->name }}">
                                            {{ strtoupper(substr($assignee->name, 0, 1)) }}
                                        </div>
                                    @endif
                                @endforeach
                                @if($task->assignees->count() > 3)
                                    <div class="w-6 h-6 rounded-full bg-gray-600 flex items-center justify-center text-[10px] font-medium text-gray-300 shadow-sm"
                                         title="{{ $task->assignees->count() - 3 }} more">
                                        +{{ $task->assignees->count() - 3 }}
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>

                    @if($task->description)
                        <p class="text-xs text-gray-400 mt-1 line-clamp-2">{{ $task->description }}</p>
                    @endif

                    <div class="flex items-center gap-3 mt-2 text-xs text-gray-500">
                        @if($task->date)
                            <span>{{ \Carbon\Carbon::parse($task->date)->format('M j') }}</span>
                        @endif
                        @if($task->tags->count() > 0)
                            <div class="flex gap-1">
                                @foreach($task->tags as $tag)
                                    <span class="inline-block px-1 py-0.5 text-xs rounded"
                                          style="background-color: {{ $tag->color }}22; color: {{ $tag->color }}">
                                        {{ $tag->tag_name }}
                                    </span>
                                @endforeach
                            </div>
                        @endif
                        @if($task->children->count() > 0)
                            <span class="text-gray-400 inline-flex items-center gap-0.5">
                                <span x-show="!subtasksOpen" style="display:none">{{ $task->children->count() }}</span><span x-show="subtasksOpen">{{ $task->incompleteChildren()->count() }}/{{ $task->children->count() }}</span> subtasks
                                <button @click.stop="subtasksOpen = !subtasksOpen"
                                        class="inline-flex items-center justify-center w-4 h-4 rounded text-gray-500 hover:text-gray-300 hover:bg-gray-600 transition"
                                        :title="subtasksOpen ? 'Collapse subtasks' : 'Expand subtasks'">
                                    <svg x-show="subtasksOpen" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M20 12H4"/></svg>
                                    <svg x-show="!subtasksOpen" class="w-3 h-3" style="display:none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                                </button>
                            </span>
                        @endif
                        @if($task->description)
                            <span class="flex items-center gap-1 text-gray-500" title="Has description">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h10"></path>
                                </svg>
                            </span>
                        @endif
                        @if($task->comments->count() > 0)
                            <span class="flex items-center gap-1 text-gray-500" title="{{ $task->comments->count() }} comment(s)">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                                </svg>
                                {{ $task->comments->count() }}
                            </span>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Nested Subtasks (recursive) -->
            @if($task->children->count() > 0)
                <div x-show="subtasksOpen" x-transition:leave="transition-opacity duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                     class="ml-8 mt-3 space-y-2">
                    <x-subtask-list :tasks="$task->children" :parent="$task" />
                </div>
            @endif
        </div>
        </div>{{-- /data-task-group --}}
    @endforeach
</div>
