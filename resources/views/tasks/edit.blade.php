<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-100 leading-tight">
            {{ __('Edit Task') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-[#202020] border border-gray-700 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form method="POST" action="{{ route('tasks.update', $task) }}">
                        @csrf
                        @method('PATCH')

                        <div class="mb-4">
                            <label for="name" class="block text-sm font-medium text-gray-300 mb-2">Task Name</label>
                            <input type="text" name="name" id="name" required
                                   class="w-full rounded-md bg-gray-700 border-gray-600 text-gray-100 placeholder-gray-500 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                   value="{{ old('name', $task->name) }}">
                            @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div class="mb-4">
                            <label for="description" class="block text-sm font-medium text-gray-300 mb-2">Description</label>
                            <textarea name="description" id="description" rows="3"
                                      class="w-full rounded-md bg-gray-700 border-gray-600 text-gray-100 placeholder-gray-500 shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('description', $task->description) }}</textarea>
                            @error('description')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div class="mb-4 grid grid-cols-2 gap-4" x-data="dateInput('{{ old('date', $task->getAttributes()['date'] ?? '') }}')">
                            <div>
                                <label for="date" class="block text-sm font-medium text-gray-300 mb-2">Date</label>
                                <div class="flex gap-2 items-start">
                                    <div class="flex-1">
                                        <input type="text" x-model="dateText" x-ref="dateTextInput"
                                               @input.debounce.300ms="previewDate()"
                                               placeholder="tomorrow, next friday, march 15, 3/15..."
                                               class="w-full rounded-md bg-gray-700 border-gray-600 text-gray-100 placeholder-gray-500 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    </div>
                                    <div class="relative flex-shrink-0">
                                        <button @click="$refs.datePicker.showPicker()" type="button"
                                                class="p-2 bg-gray-700 border border-gray-600 rounded-md hover:bg-gray-600 text-gray-400 hover:text-gray-200"
                                                title="Open calendar">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                            </svg>
                                        </button>
                                        <input type="date" x-ref="datePicker"
                                               @change="pickDate($event.target.value)"
                                               class="absolute inset-0 opacity-0 w-full h-full cursor-pointer">
                                    </div>
                                    <button x-show="dateText || resolvedDate" @click="clearDate()" type="button"
                                            class="px-3 py-1 bg-gray-700 text-red-400 text-sm rounded hover:bg-gray-600 flex-shrink-0">
                                        Clear
                                    </button>
                                </div>
                                <input type="hidden" name="date" :value="resolvedDate || dateText">
                                <div x-show="datePreview" class="mt-1 text-xs text-green-400" x-text="datePreview"></div>
                                <div x-show="dateError" class="mt-1 text-xs text-red-400" x-text="dateError"></div>
                                <p class="mt-1 text-xs text-gray-500">Accepts: tomorrow, next friday, march 15, 3/15, 2026-03-15</p>
                                @error('date')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="time" class="block text-sm font-medium text-gray-300 mb-2">Time (Optional)</label>
                                <input type="time" name="time" id="time"
                                       class="w-full rounded-md bg-gray-700 border-gray-600 text-gray-100 placeholder-gray-500 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                       value="{{ old('time', $task->getAttributes()['time'] ?? '') }}">
                                <p class="mt-1 text-xs text-gray-500">Leave blank for all-day tasks.</p>
                                @error('time')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="duration_minutes" class="block text-sm font-medium text-gray-300 mb-2">Duration (Optional)</label>
                            <input type="text" name="duration_minutes" id="duration_minutes"
                                   class="w-40 rounded-md bg-gray-700 border-gray-600 text-gray-100 placeholder-gray-500 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                   value="{{ old('duration_minutes', \App\Models\Task::formatDuration($task->duration_minutes) ?? '') }}"
                                   placeholder="e.g., 1h 30m">
                            <p class="mt-1 text-xs text-gray-500">How long this task takes (e.g. 90, 1h 30m, 2h). Used in the agenda view to size the block.</p>
                            @error('duration_minutes')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div class="mb-4">
                            <label for="recurrence_pattern" class="block text-sm font-medium text-gray-300 mb-2">Recurrence Pattern</label>
                            <input type="text" name="recurrence_pattern" id="recurrence_pattern"
                                   class="w-full rounded-md bg-gray-700 border-gray-600 text-gray-100 placeholder-gray-500 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                   value="{{ old('recurrence_pattern', $task->recurrence_pattern) }}"
                                   placeholder="e.g., daily, every Monday, weekdays">
                            <p class="mt-1 text-xs text-gray-400">Supported: daily, every other day, weekdays, weekends, weekly, monthly, every Monday/Tuesday/etc., every other Wednesday, every 2 weeks, every 3 months, every 15th, every first Monday, yearly</p>
                            @error('recurrence_pattern')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            <label class="flex items-center mt-2 text-sm text-gray-400 cursor-pointer">
                                <input type="hidden" name="recurrence_floating" value="0">
                                <input type="checkbox" name="recurrence_floating" value="1"
                                       {{ old('recurrence_floating', $task->recurrence_floating) ? 'checked' : '' }}
                                       class="rounded bg-gray-700 border-gray-600 text-purple-500 focus:ring-purple-500 mr-2">
                                Floating (next date relative to when completed, not the original due date)
                            </label>
                        </div>

                        <div class="mb-4">
                            <label for="status" class="block text-sm font-medium text-gray-300 mb-2">Status</label>
                            <select name="status" id="status"
                                    class="w-full rounded-md bg-gray-700 border-gray-600 text-gray-100 placeholder-gray-500 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="incomplete" {{ old('status', $task->status) === 'incomplete' ? 'selected' : '' }}>Incomplete</option>
                                <option value="done" {{ old('status', $task->status) === 'done' ? 'selected' : '' }}>Done</option>
                                <option value="archived" {{ old('status', $task->status) === 'archived' ? 'selected' : '' }}>Archived</option>
                            </select>
                            @error('status')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div class="mb-4">
                            <label for="project_id" class="block text-sm font-medium text-gray-300 mb-2">Project</label>
                            <select name="project_id" id="project_id"
                                    class="w-full rounded-md bg-gray-700 border-gray-600 text-gray-100 placeholder-gray-500 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                @foreach($projects as $project)
                                    <option value="{{ $project->id }}" {{ old('project_id', $task->project_id ?? $inboxProjectId) == $project->id ? 'selected' : '' }}>
                                        {{ $project->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('project_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <!-- Parent Task -->
                        <div class="mb-4">
                            <label for="parent_id" class="block text-sm font-medium text-gray-300 mb-2">
                                Parent Task (Optional - create as subtask)
                            </label>
                            <select name="parent_id" id="parent_id"
                                    class="w-full rounded-md bg-gray-700 border-gray-600 text-gray-100 placeholder-gray-500 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">None (Top-level task)</option>
                                @foreach($availableParents as $parentOption)
                                    <option value="{{ $parentOption->id }}" {{ old('parent_id', $task->parent_id) == $parentOption->id ? 'selected' : '' }}>
                                        {{ str_repeat('→ ', $parentOption->getDepth()) }}{{ $parentOption->name }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-gray-500">
                                Select a parent task to make this a subtask. Subtasks inherit permissions from their parent.
                            </p>
                            @error('parent_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-300 mb-2">Tags</label>
                            <div class="space-y-2">
                                @foreach($tags as $tag)
                                    <label class="inline-flex items-center mr-4">
                                        <input type="checkbox" name="tag_ids[]" value="{{ $tag->id }}"
                                               class="rounded border-gray-600 bg-gray-700 text-blue-600 focus:ring-blue-500"
                                               {{ in_array($tag->id, old('tag_ids', $task->tags->pluck('id')->toArray())) ? 'checked' : '' }}>
                                        <span class="ml-2 text-sm" style="color: {{ $tag->color }}">{{ $tag->tag_name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        @if($task->creator_id === Auth::id())
                            <div class="mb-6">
                                <label class="block text-sm font-medium text-gray-300 mb-2">Assign To</label>
                                <div class="space-y-2">
                                    @foreach($users as $user)
                                        <label class="flex items-center {{ $user->id === Auth::id() ? 'cursor-not-allowed opacity-60' : '' }}">
                                            <input type="checkbox" name="assignee_ids[]" value="{{ $user->id }}"
                                                   class="rounded border-gray-600 bg-gray-700 text-blue-600 focus:ring-blue-500"
                                                   {{ in_array($user->id, old('assignee_ids', $task->assignees->pluck('id')->toArray())) ? 'checked' : '' }}
                                                   @if($user->id === Auth::id()) disabled title="You cannot remove yourself as you are the task creator" @endif>
                                            <span class="ml-2 text-sm text-gray-300">{{ $user->name }} ({{ $user->email }})</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <div class="flex items-center gap-4">
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700">
                                Update Task
                            </button>
                            <a href="{{ route('tasks.show', $task) }}" class="text-sm text-gray-400 hover:text-gray-300">
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function dateInput(initialDate) {
            return {
                dateText: '',
                resolvedDate: initialDate || '',
                datePreview: '',
                dateError: '',
                taskCount: null,

                init() {
                    if (this.resolvedDate && /^\d{4}-\d{2}-\d{2}$/.test(this.resolvedDate)) {
                        const d = new Date(this.resolvedDate + 'T12:00:00');
                        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
                        this.dateText = d.toLocaleDateString('en-US', options);
                        this.previewDate();
                    }
                },

                formatPreview(formatted, count) {
                    if (count === null || count === undefined) return formatted;
                    const label = count === 1 ? '1 task' : `${count} tasks`;
                    return `${formatted} \u2014 ${label}`;
                },

                async previewDate() {
                    const input = this.dateText.trim();
                    if (!input) {
                        this.datePreview = '';
                        this.dateError = '';
                        this.resolvedDate = '';
                        this.taskCount = null;
                        return;
                    }

                    try {
                        const response = await fetch('{{ route("tasks.parseDate") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({ input: input }),
                        });

                        const data = await response.json();
                        if (data.success) {
                            this.taskCount = data.taskCount ?? null;
                            this.datePreview = this.formatPreview(data.formatted, this.taskCount);
                            this.dateError = '';
                            this.resolvedDate = data.date;
                        } else {
                            this.datePreview = '';
                            this.dateError = 'Could not parse this date';
                            this.resolvedDate = '';
                            this.taskCount = null;
                        }
                    } catch (e) {
                        this.datePreview = '';
                        this.dateError = '';
                        this.taskCount = null;
                    }
                },

                async pickDate(value) {
                    if (!value) return;
                    const d = new Date(value + 'T12:00:00');
                    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
                    this.dateText = d.toLocaleDateString('en-US', options);
                    this.resolvedDate = value;
                    this.dateError = '';

                    try {
                        const response = await fetch('{{ route("tasks.parseDate") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({ input: value }),
                        });
                        const data = await response.json();
                        this.taskCount = data.success ? (data.taskCount ?? null) : null;
                    } catch (e) {
                        this.taskCount = null;
                    }

                    this.datePreview = this.formatPreview(this.dateText, this.taskCount);
                },

                clearDate() {
                    this.dateText = '';
                    this.resolvedDate = '';
                    this.datePreview = '';
                    this.dateError = '';
                    this.taskCount = null;
                },
            };
        }
    </script>
    @endpush
</x-app-layout>
