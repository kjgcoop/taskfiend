<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-100 leading-tight">
            {{ __('Create Task') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-[#202020] overflow-hidden shadow-sm sm:rounded-lg border border-gray-700">
                <div class="p-6"
                     x-data="taskCreator"
                     data-projects="{{ json_encode($projects) }}"
                     data-tags="{{ json_encode($tags) }}">
                    {{-- Bulk-creation result banner (shown after partial success redirect) --}}
                    @if(session('bulk_errors'))
                    <div class="mb-6 p-4 bg-red-900/30 border border-red-700 rounded-md">
                        @if(session('success'))
                        <p class="text-sm font-medium text-green-400 mb-2">{{ session('success') }}</p>
                        @endif
                        <p class="text-sm font-medium text-red-300 mb-2">
                            The following {{ count(session('bulk_errors')) === 1 ? 'line' : 'lines' }} could not be created:
                        </p>
                        <ul class="text-sm text-red-400 space-y-1">
                            @foreach(session('bulk_errors') as $err)
                            <li>
                                <span class="font-mono bg-gray-800 px-1 rounded text-xs">Line {{ $err['line'] }}</span>
                                <span class="font-medium">"{{ $err['input'] }}"</span>:
                                {{ $err['error'] }}
                            </li>
                            @endforeach
                        </ul>
                        <p class="text-xs text-gray-500 mt-2">The failed lines are pre-filled below. Fix them and try again.</p>
                    </div>
                    @elseif(session('success'))
                    <div class="mb-6 p-4 bg-green-900/30 border border-green-700 rounded-md">
                        <p class="text-sm text-green-400">{{ session('success') }}</p>
                    </div>
                    @endif

                    <form method="POST" action="{{ route('tasks.store') }}" @submit="prepareSubmit">
                        @csrf

                        <div class="mb-4 relative">
                            <label for="name" class="block text-sm font-medium text-gray-300 mb-2">
                                Task Name
                                <span class="text-xs text-gray-500 font-normal">(use #project or @tag to auto-select)</span>
                            </label>
                            <textarea x-model="taskName"
                                      @input="handleInput"
                                      @keydown="handleKeydown($event)"
                                      @blur="hideAutocomplete"
                                      id="name"
                                      x-ref="nameInput"
                                      required
                                      rows="1"
                                      :class="nameError ? 'border-red-500 focus:border-red-500 focus:ring-red-500' : 'border-gray-600 focus:border-blue-500 focus:ring-blue-500'"
                                      style="resize: none; overflow: hidden;"
                                      class="w-full rounded-md bg-gray-700 text-gray-100 placeholder-gray-500 shadow-sm"
                                      placeholder="e.g., Meeting #work @urgent&#10;Paste or Shift+Enter for multiple tasks"></textarea>

                            <!-- Autocomplete Dropdown -->
                            <div x-show="showAutocomplete"
                                 x-transition
                                 class="absolute z-10 mt-1 w-full bg-gray-700 border border-gray-600 rounded-md shadow-lg max-h-60 overflow-auto">
                                <template x-if="autocompleteType === 'project'">
                                    <div>
                                        <div class="px-3 py-2 text-xs font-semibold text-gray-400 bg-[#202020] border-b border-gray-600">Projects</div>
                                        <template x-for="(project, index) in filteredProjects" :key="project.id">
                                            <div class="px-2 py-1 hover:bg-gray-600 cursor-pointer text-sm text-gray-300"
                                                 @click.prevent="selectAutocomplete(project.name)"
                                                 :class="{ 'bg-gray-600': autocompleteIndex === index }">
                                                <span x-text="project.name"></span>
                                            </div>
                                        </template>
                                        <div x-show="filteredProjects.length === 0" class="px-3 py-2 text-sm text-gray-500 italic">
                                            No matching projects
                                        </div>
                                    </div>
                                </template>

                                <template x-if="autocompleteType === 'tag'">
                                    <div>
                                        <div class="px-3 py-2 text-xs font-semibold text-gray-400 bg-[#202020] border-b border-gray-600">Tags</div>
                                        <template x-for="(tag, index) in filteredTags" :key="tag.id">
                                            <div class="px-2 py-1 hover:bg-gray-600 cursor-pointer text-sm flex items-center"
                                                 @click.prevent="selectAutocomplete(tag.tag_name)"
                                                 :class="{ 'bg-gray-600': autocompleteIndex === index }">
                                                <span :style="'color: ' + tag.color" x-text="tag.tag_name"></span>
                                            </div>
                                        </template>
                                        <template x-if="autocompleteQuery.length > 0 && !hasExactTagMatch">
                                            <div class="border-t border-gray-600">
                                                <div class="px-2 py-1 hover:bg-gray-600 cursor-pointer text-sm flex items-center text-green-400"
                                                     @click.prevent="createAndSelectTag(autocompleteQuery)"
                                                     :class="{ 'bg-gray-600': autocompleteIndex === filteredTags.length }">
                                                    <span class="mr-1">+</span>
                                                    Create new tag "<span x-text="autocompleteQuery" class="font-semibold"></span>"?
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                            </div>

                            <div class="mt-1 flex justify-between items-baseline gap-2">
                                <p class="text-xs text-gray-500">
                                    Type <code class="bg-gray-700 px-1 rounded text-gray-300">#</code> for projects or
                                    <code class="bg-gray-700 px-1 rounded text-gray-300">@</code> for tags.
                                    <span x-show="lineCount <= 1">Use the Date and Recurrence fields below for scheduling.</span>
                                    <span x-show="lineCount > 1" x-cloak class="text-blue-400">Each line becomes a separate task. The Project, Date, and other fields below apply to any task without inline overrides.</span>
                                </p>
                                <span x-show="lineCount <= 1"
                                      class="text-xs ml-2 flex-shrink-0"
                                      :class="nameError ? 'text-red-400 font-semibold' : 'text-gray-500'"
                                      x-text="taskName.length + '/255'"></span>
                                <span x-show="lineCount > 1" x-cloak
                                      class="text-xs ml-2 flex-shrink-0 text-blue-400 font-medium"
                                      x-text="lineCount + ' tasks'"></span>
                            </div>
                            <p x-show="nameError" x-text="nameError" class="mt-1 text-sm text-red-400"></p>
                            @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <!-- Hidden field to submit the (cleaned) task name -->
                        <input type="hidden" name="name" x-model="submittableName">

                        <div class="mb-4">
                            <label for="description" class="block text-sm font-medium text-gray-300 mb-2">Description</label>
                            <textarea name="description" id="description" rows="3"
                                      class="w-full rounded-md bg-gray-700 border-gray-600 text-gray-100 placeholder-gray-500 shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('description') }}</textarea>
                            @error('description')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div class="mb-4">
                            <label for="location" class="block text-sm font-medium text-gray-300 mb-2">Location (Optional)</label>
                            <input type="text" name="location" id="location"
                                   class="w-full rounded-md bg-gray-700 border-gray-600 text-gray-100 placeholder-gray-500 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                   value="{{ old('location') }}"
                                   placeholder="e.g., Conference Room B, Zoom, 123 Main St">
                            @error('location')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div class="mb-4 grid grid-cols-2 gap-4" x-data="dateInput" data-initial-date="{{ old('date', $preselectedDate) }}">
                            <div>
                                <label for="date" class="block text-sm font-medium text-gray-300 mb-2">Date (Optional)</label>
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
                                <div x-show="datePreview" :class="datePast ? 'text-red-400' : 'text-green-400'" class="mt-1 text-xs flex flex-wrap items-baseline gap-x-1">
                                    <span x-text="datePreview"></span>
                                    <span x-show="projects && projects.length > 0" class="flex flex-wrap items-baseline gap-x-1">
                                        <span class="text-gray-500">&mdash;</span>
                                        <span x-text="projectTaskCount + (projectTaskCount === 1 ? ' task' : ' tasks')"></span>
                                        <template x-for="proj in (projects || [])" :key="proj.name">
                                            <span class="relative group inline-flex items-baseline gap-x-0.5">
                                                <span class="text-gray-500">·</span>
                                                <span class="underline decoration-dotted cursor-help" x-text="proj.name + ' \u00d7' + proj.count"></span>
                                                <div class="absolute hidden group-hover:block bottom-full left-0 mb-1 bg-gray-900 border border-gray-600 rounded p-2 text-gray-200 z-50 shadow-lg min-w-max max-w-xs">
                                                    <template x-for="(task, idx) in proj.tasks" :key="idx">
                                                        <div class="flex gap-1.5 py-0.5">
                                                            <span class="flex-shrink-0 text-gray-500">·</span>
                                                            <span x-text="task"></span>
                                                        </div>
                                                    </template>
                                                    <div x-show="proj.more > 0" x-text="'+' + proj.more + ' more'" class="text-gray-400 italic mt-1 py-0.5"></div>
                                                </div>
                                            </span>
                                        </template>
                                    </span>
                                </div>
                                <div x-show="dateError" class="mt-1 text-xs text-red-400" x-text="dateError"></div>
                                <p class="mt-1 text-xs text-gray-500">Accepts: tomorrow, next friday, march 15, 3/15, 2026-03-15</p>
                                @error('date')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="time" class="block text-sm font-medium text-gray-300 mb-2">Time (Optional)</label>
                                <input type="time" name="time" id="time"
                                       class="w-full rounded-md bg-gray-700 border-gray-600 text-gray-100 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                       value="{{ old('time') }}">
                                <p class="mt-1 text-xs text-gray-500">Optional - leave blank for all-day tasks.</p>
                                @error('time')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="duration_minutes" class="block text-sm font-medium text-gray-300 mb-2">Duration (Optional)</label>
                            <input type="text" name="duration_minutes" id="duration_minutes"
                                   class="w-40 rounded-md bg-gray-700 border-gray-600 text-gray-100 placeholder-gray-500 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                   value="{{ old('duration_minutes') }}"
                                   placeholder="e.g., 1h 30m">
                            <p class="mt-1 text-xs text-gray-500">How long this task takes (e.g. 90, 1h 30m, 2h). Used in the agenda view to size the block.</p>
                            @error('duration_minutes')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div class="mb-4">
                            <label for="recurrence_pattern" class="block text-sm font-medium text-gray-300 mb-2">Recurrence (Optional)</label>
                            <input type="text" name="recurrence_pattern" id="recurrence_pattern"
                                   class="w-full rounded-md bg-gray-700 border-gray-600 text-gray-100 placeholder-gray-500 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                   value="{{ old('recurrence_pattern') }}"
                                   placeholder="e.g., daily, every Monday, weekdays">
                            <p class="mt-1 text-xs text-gray-500">Use "every!" for floating recurrence (e.g., "every! 3 weeks").</p>
                            <p class="mt-1 text-xs text-gray-400">Supported: daily, every other day, weekdays, weekends, weekly, monthly, every Monday/Tuesday/etc., every other Wednesday, every 2 weeks, every 3 months, every 15th, every first Monday, yearly</p>
                            @error('recurrence_pattern')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            <label class="flex items-center mt-2 text-sm text-gray-400 cursor-pointer">
                                <input type="hidden" name="recurrence_floating" value="0">
                                <input type="checkbox" name="recurrence_floating" value="1"
                                       {{ old('recurrence_floating') ? 'checked' : '' }}
                                       class="rounded bg-gray-700 border-gray-600 text-purple-500 focus:ring-purple-500 mr-2">
                                Floating (next date relative to when completed, not the original due date)
                            </label>
                        </div>

                        <div class="mb-4">
                            <label for="project_id" class="block text-sm font-medium text-gray-300 mb-2">Project</label>
                            <select x-model="selectedProjectId"
                                    name="project_id"
                                    id="project_id"
                                    class="w-full rounded-md bg-gray-700 border-gray-600 text-gray-100 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                @foreach($projects as $project)
                                    <option value="{{ $project->id }}">{{ $project->name }}</option>
                                @endforeach
                            </select>
                            @error('project_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <!-- Parent Task (Subtask) -->
                        @if(isset($preselectedParentId) && $preselectedParentId)
                        <div class="mb-4">
                            <label for="parent_id" class="block text-sm font-medium text-gray-300 mb-2">
                                Parent Task (Creating Subtask)
                            </label>
                            <div class="p-3 bg-gray-700 border border-gray-600 rounded-md">
                                <p class="text-sm text-gray-300">
                                    <span class="text-gray-500">Subtask of:</span>
                                    <a href="{{ route('tasks.show', $preselectedParentTask) }}"
                                       class="text-blue-400 hover:underline ml-1"
                                       target="_blank">
                                        {{ $preselectedParentTask->name }}
                                    </a>
                                </p>
                                <p class="text-xs text-gray-500 mt-1">
                                    This task will inherit assignees from the parent unless you specify different ones below.
                                </p>
                            </div>
                            <input type="hidden" name="parent_id" value="{{ $preselectedParentId }}">
                        </div>
                        @else
                        @php
                            $_oldParentId = old('parent_id', '');
                            $_oldParentName = '';
                            if ($_oldParentId) {
                                $_found = $availableParents->firstWhere('id', $_oldParentId);
                                $_oldParentName = $_found ? $_found->name : '';
                            }
                            $_parentsForCombo = $availableParents->map(fn($t) => [
                                'id' => $t->id,
                                'name' => str_repeat('→ ', $t->getDepth()) . $t->name,
                                'rawName' => $t->name,
                            ])->values()->all();
                        @endphp
                        <div class="mb-4"
                             x-data="parentTaskCombo" @click.outside="open = false">
                            <label class="block text-sm font-medium text-gray-300 mb-2">
                                Parent Task <span class="font-normal text-gray-500">(Optional – type to search)</span>
                            </label>
                            <div class="relative">
                                <input type="text"
                                       x-model="search"
                                       @input="onInput"
                                       @focus="open = true"
                                       @keydown.escape="open = false"
                                       @keydown.enter.prevent="if (filtered.length > 0) select(filtered[0])"
                                       placeholder="Search for a parent task…"
                                       autocomplete="off"
                                       class="w-full rounded-md bg-gray-700 border-gray-600 text-gray-100 placeholder-gray-500 shadow-sm focus:border-blue-500 focus:ring-blue-500 pr-8">
                                <button type="button" x-show="selectedId || search"
                                        @click="clear()"
                                        class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-100 text-xl leading-none">
                                    &times;
                                </button>
                                <div x-show="open" x-cloak
                                     class="absolute z-50 w-full mt-1 bg-gray-800 border border-gray-600 rounded-md shadow-lg max-h-60 overflow-y-auto">
                                    <div @mousedown.prevent="clear(); open = false"
                                         class="px-3 py-2 text-sm text-gray-400 cursor-pointer hover:bg-gray-700 border-b border-gray-700">
                                        None (Top-level task)
                                    </div>
                                    <template x-for="task in filtered" :key="task.id">
                                        <div @mousedown.prevent="select(task)"
                                             class="px-3 py-2 text-sm text-gray-100 cursor-pointer hover:bg-gray-700"
                                             :class="{ 'bg-gray-600': selectedId == task.id }">
                                            <span x-text="task.name"></span>
                                        </div>
                                    </template>
                                    <div x-show="search && filtered.length === 0"
                                         class="px-3 py-2 text-sm text-gray-500 italic">
                                        No matching tasks found
                                    </div>
                                </div>
                            </div>
                            <input type="hidden" name="parent_id" :value="selectedId">
                            <p class="mt-1 text-xs text-gray-500">
                                Search for a parent task to create this as a subtask. Subtasks inherit permissions from their parent.
                            </p>
                            @error('parent_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        @endif

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-300 mb-2">Tags</label>
                            <div class="space-y-2">
                                <template x-for="tag in tags" :key="tag.id">
                                    <label class="inline-flex items-center mr-4">
                                        <input type="checkbox"
                                               name="tag_ids[]"
                                               :value="tag.id"
                                               :checked="selectedTagIds.includes(tag.id)"
                                               @change="toggleTag(tag.id)"
                                               class="rounded border-gray-600 bg-gray-700 text-blue-600 focus:ring-blue-500">
                                        <span class="ml-2 text-sm" :style="'color: ' + tag.color" x-text="tag.tag_name"></span>
                                    </label>
                                </template>
                            </div>
                        </div>

                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-300 mb-2">Assign To</label>
                            <div class="space-y-2">
                                @foreach($users as $user)
                                    <label class="flex items-center">
                                        <input type="checkbox" name="assignee_ids[]" value="{{ $user->id }}"
                                               class="rounded border-gray-600 bg-gray-700 text-blue-600 focus:ring-blue-500"
                                               {{ in_array($user->id, old('assignee_ids', [Auth::id()])) ? 'checked' : '' }}>
                                        <span class="ml-2 text-sm text-gray-300">{{ $user->name }} ({{ $user->email }})</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div class="flex items-center gap-4">
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700">
                                <span x-show="lineCount <= 1">Create Task</span>
                                <span x-show="lineCount > 1" x-cloak x-text="'Create ' + lineCount + ' Tasks'"></span>
                            </button>
                            <a href="{{ route('day') }}" class="text-sm text-gray-400 hover:text-gray-100">
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script nonce="{{ csp_nonce() }}">
        document.addEventListener('alpine:init', () => {
        Alpine.data('dateInput', function() {
            return {
                dateText: '',
                resolvedDate: '',
                datePreview: '',
                dateError: '',
                datePast: false,
                projects: null,

                get projectTaskCount() {
                    let n = 0;
                    if (this.projects) { for (const p of this.projects) n += p.count; }
                    return n;
                },

                init() {
                    const initialDate = this.$el.dataset.initialDate || '';
                    this.resolvedDate = initialDate;
                    // Convert Y-m-d initial value to human-readable text
                    if (this.resolvedDate && /^\d{4}-\d{2}-\d{2}$/.test(this.resolvedDate)) {
                        const d = new Date(this.resolvedDate + 'T12:00:00');
                        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
                        this.dateText = d.toLocaleDateString('en-US', options);
                        this.previewDate();
                    }
                },

                async previewDate() {
                    const input = this.dateText.trim();
                    if (!input) {
                        this.datePreview = '';
                        this.dateError = '';
                        this.resolvedDate = '';
                        this.projects = null;
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
                            const today = new Date();
                            today.setHours(0, 0, 0, 0);
                            const resolved = new Date(data.date + 'T00:00:00');
                            if (resolved < today) {
                                this.datePast = true;
                                this.datePreview = 'Proposed date is in the past';
                                this.resolvedDate = data.date;
                                this.projects = null;
                            } else {
                                this.datePast = false;
                                this.projects = data.projects ?? null;
                                this.datePreview = data.formatted;
                                this.dateError = '';
                                this.resolvedDate = data.date;
                            }
                        } else {
                            this.datePast = false;
                            this.datePreview = '';
                            this.dateError = 'Could not parse this date';
                            this.resolvedDate = '';
                            this.projects = null;
                        }
                    } catch (e) {
                        this.datePast = false;
                        this.datePreview = '';
                        this.dateError = '';
                        this.projects = null;
                    }
                },

                async pickDate(value) {
                    if (!value) return;
                    const d = new Date(value + 'T12:00:00');
                    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
                    this.dateText = d.toLocaleDateString('en-US', options);
                    this.resolvedDate = value;
                    this.dateError = '';
                    this.projects = null;

                    const today = new Date();
                    today.setHours(0, 0, 0, 0);
                    if (d < today) {
                        this.datePast = true;
                        this.datePreview = 'Proposed date is in the past';
                        return;
                    }
                    this.datePast = false;

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
                        this.projects = data.success ? (data.projects ?? null) : null;
                    } catch (e) {
                        this.projects = null;
                    }

                    this.datePreview = this.dateText;
                },

                clearDate() {
                    this.dateText = '';
                    this.resolvedDate = '';
                    this.datePreview = '';
                    this.dateError = '';
                    this.datePast = false;
                    this.projects = null;
                },
            };
        });
        });

        function taskCreator() {
            return {
                projects: [],
                tags: [],
                taskName: @js(old('name', '')),
                selectedProjectId: @js(old('project_id', $preselectedProjectId ?? '')),
                selectedTagIds: @js(old('tag_ids', [])),

                nameError: '',

                // Autocomplete state
                showAutocomplete: false,
                autocompleteType: null,
                autocompleteIndex: 0,
                autocompleteQuery: '',
                confirmedTagSlugs: [],
                confirmedProjectSlugs: [],

                // Number of non-empty lines — drives multi-task UI
                get lineCount() {
                    return this.taskName.split('\n').filter(l => l.trim().length > 0).length;
                },

                get cleanedTaskName() {
                    let name = this.taskName;
                    // Only remove @slugs that were actually selected as tags
                    for (const slug of this.confirmedTagSlugs) {
                        name = name.replace(new RegExp('@' + slug + '(?=\\s|$)', 'g'), '');
                    }
                    // Only remove #slugs that were actually matched to a project via autocomplete
                    for (const slug of this.confirmedProjectSlugs) {
                        name = name.replace(new RegExp('#' + slug + '(?=\\s|$)', 'g'), '');
                    }
                    return name.trim().replace(/\s+/g, ' ');
                },

                // What gets submitted: raw multi-line text in bulk mode (server handles all
                // per-line parsing); cleaned single-line text in normal mode.
                get submittableName() {
                    if (this.lineCount > 1) return this.taskName;
                    return this.cleanedTaskName;
                },

                init() {
                    const el = this.$el;
                    this.projects = JSON.parse(el.dataset.projects || '[]');
                    this.tags = JSON.parse(el.dataset.tags || '[]');
                    // Auto-resize the textarea when the page loads with a pre-filled value
                    this.$nextTick(() => {
                        const input = this.$refs.nameInput;
                        if (input && input.value) {
                            input.style.height = 'auto';
                            input.style.height = Math.min(input.scrollHeight, 240) + 'px';
                        }
                    });
                },

                get filteredProjects() {
                    if (!this.autocompleteQuery) return this.projects;
                    const query = this.autocompleteQuery.toLowerCase();
                    return this.projects.filter(p =>
                        p.name.toLowerCase().includes(query)
                    );
                },

                get filteredTags() {
                    if (!this.autocompleteQuery) return this.tags;
                    const query = this.autocompleteQuery.toLowerCase();
                    return this.tags.filter(t =>
                        t.tag_name.toLowerCase().includes(query)
                    );
                },

                get hasExactTagMatch() {
                    const query = this.autocompleteQuery.toLowerCase();
                    return this.tags.some(t => t.tag_name.toLowerCase() === query);
                },

                handleInput(event) {
                    const el = event.target;

                    // Auto-resize textarea to fit content
                    el.style.height = 'auto';
                    el.style.height = Math.min(el.scrollHeight, 240) + 'px';

                    // Per-line length validation
                    const lines = this.taskName.split('\n').filter(l => l.trim().length > 0);
                    const tooLong = lines.find(l => l.trim().length > 255);
                    this.nameError = tooLong
                        ? `A task name is too long (${tooLong.trim().length}/255 characters max).`
                        : '';

                    const input = this.taskName;
                    const cursorPos = el.selectionStart;

                    // Scope autocomplete detection to the current line before the cursor
                    const lineStart = input.lastIndexOf('\n', cursorPos - 1) + 1;
                    const beforeCursor = input.substring(lineStart, cursorPos);

                    const projectMatch = beforeCursor.match(/#(\w*)$/);
                    const tagMatch = beforeCursor.match(/@(\w*)$/);

                    if (projectMatch) {
                        this.autocompleteType = 'project';
                        this.autocompleteQuery = projectMatch[1];
                        this.autocompleteIndex = 0;
                        this.showAutocomplete = true;
                    } else if (tagMatch) {
                        this.autocompleteType = 'tag';
                        this.autocompleteQuery = tagMatch[1];
                        this.autocompleteIndex = 0;
                        this.showAutocomplete = true;
                    } else {
                        this.showAutocomplete = false;
                    }
                },

                handleKeydown(event) {
                    if (!this.showAutocomplete) {
                        if (event.key === 'Enter' && !event.shiftKey) {
                            event.preventDefault();
                            event.target.closest('form').requestSubmit();
                        }
                        return;
                    }

                    let maxIndex;
                    if (this.autocompleteType === 'project') {
                        maxIndex = this.filteredProjects.length - 1;
                    } else {
                        // Add 1 for the "create new tag" option if it's visible
                        const hasCreate = this.autocompleteQuery.length > 0 && !this.hasExactTagMatch;
                        maxIndex = this.filteredTags.length - 1 + (hasCreate ? 1 : 0);
                    }

                    if (event.key === 'ArrowDown') {
                        event.preventDefault();
                        this.autocompleteIndex = Math.min(this.autocompleteIndex + 1, maxIndex);
                    } else if (event.key === 'ArrowUp') {
                        event.preventDefault();
                        this.autocompleteIndex = Math.max(this.autocompleteIndex - 1, 0);
                    } else if (event.key === 'Enter' && this.showAutocomplete) {
                        event.preventDefault();

                        if (this.autocompleteType === 'project') {
                            const selected = this.filteredProjects[this.autocompleteIndex]?.name;
                            if (selected) this.selectAutocomplete(selected);
                        } else if (this.autocompleteType === 'tag') {
                            // If index is past the filtered tags, it's the "create" option
                            if (this.autocompleteIndex >= this.filteredTags.length) {
                                this.createAndSelectTag(this.autocompleteQuery);
                            } else {
                                const selected = this.filteredTags[this.autocompleteIndex]?.tag_name;
                                if (selected) this.selectAutocomplete(selected);
                            }
                        }
                    } else if (event.key === 'Escape') {
                        event.preventDefault();
                        this.showAutocomplete = false;
                    }
                },

                selectAutocomplete(name) {
                    const input = this.taskName;
                    const inputEl = this.$refs.nameInput;
                    const cursorPos = inputEl.selectionStart;
                    const beforeCursor = input.substring(0, cursorPos);
                    const afterCursor = input.substring(cursorPos);
                    const isMultiLine = this.lineCount > 1;

                    // Replace the incomplete word with the selected name
                    let newBefore;
                    let slug = name.toLowerCase().replace(/[^a-z0-9]/g, '');

                    if (this.autocompleteType === 'project') {
                        newBefore = beforeCursor.replace(/#\w*$/, '#' + slug + ' ');
                        this.confirmedProjectSlugs.push(slug);

                        // In single-line mode, also auto-select the project dropdown.
                        // In multi-line mode the server handles per-line project assignment.
                        if (!isMultiLine) {
                            const project = this.projects.find(p =>
                                p.name.toLowerCase().replace(/[^a-z0-9]/g, '') === slug
                            );
                            if (project) {
                                this.selectedProjectId = project.id;
                            }
                        }
                    } else {
                        newBefore = beforeCursor.replace(/@\w*$/, '@' + slug + ' ');

                        // In single-line mode, also tick the tag checkbox.
                        // In multi-line mode the server parses @tags per line additively.
                        if (!isMultiLine) {
                            const tag = this.tags.find(t =>
                                t.tag_name.toLowerCase().replace(/[^a-z0-9]/g, '') === slug
                            );
                            if (tag && !this.selectedTagIds.includes(tag.id)) {
                                this.selectedTagIds.push(tag.id);
                            }
                            this.confirmedTagSlugs.push(slug);
                        }
                    }

                    this.taskName = newBefore + afterCursor;
                    this.showAutocomplete = false;

                    // Refocus input
                    this.$nextTick(() => {
                        inputEl.focus();
                        inputEl.setSelectionRange(newBefore.length, newBefore.length);
                    });
                },

                async createAndSelectTag(tagName) {
                    try {
                        const response = await fetch('{{ route("tags.quickStore") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({ tag_name: tagName }),
                        });

                        if (!response.ok) {
                            const err = await response.json();
                            alert(err.message || 'Failed to create tag.');
                            return;
                        }

                        const newTag = await response.json();

                        // Add to local tags list
                        this.tags.push(newTag);

                        // Select it as if the user chose it from autocomplete
                        this.selectedTagIds.push(newTag.id);
                        this.confirmedTagSlugs.push(newTag.tag_name.toLowerCase().replace(/[^a-z0-9]/g, ''));

                        // Replace @query in the task name
                        const inputEl = this.$refs.nameInput;
                        const cursorPos = inputEl.selectionStart;
                        const beforeCursor = this.taskName.substring(0, cursorPos);
                        const afterCursor = this.taskName.substring(cursorPos);
                        const slug = newTag.tag_name.toLowerCase().replace(/[^a-z0-9]/g, '');
                        const newBefore = beforeCursor.replace(/@\w*$/, '@' + slug + ' ');

                        this.taskName = newBefore + afterCursor;
                        this.showAutocomplete = false;

                        this.$nextTick(() => {
                            inputEl.focus();
                            inputEl.setSelectionRange(newBefore.length, newBefore.length);
                        });
                    } catch (e) {
                        alert('Failed to create tag. Please try again.');
                    }
                },

                hideAutocomplete() {
                    setTimeout(() => {
                        this.showAutocomplete = false;
                    }, 200);
                },

                toggleTag(tagId) {
                    if (this.selectedTagIds.includes(tagId)) {
                        this.selectedTagIds = this.selectedTagIds.filter(id => id !== tagId);
                    } else {
                        this.selectedTagIds.push(tagId);
                    }
                },

                prepareSubmit(e) {
                    const lines = this.taskName.split('\n').filter(l => l.trim().length > 0);
                    if (lines.length > 1) {
                        const tooLong = lines.find(l => l.trim().length > 255);
                        if (tooLong) {
                            e.preventDefault();
                            this.nameError = `A task name is too long (${tooLong.trim().length}/255 characters max).`;
                            this.$refs.nameInput.focus();
                        }
                    } else if (this.taskName.length > 255) {
                        e.preventDefault();
                        this.nameError = `Task name is too long (${this.taskName.length}/255 characters)`;
                        this.$refs.nameInput.focus();
                    }
                }
            };
        }
    </script>
    <script nonce="{{ csp_nonce() }}">
    document.addEventListener('alpine:init', () => {
        Alpine.data('parentTaskCombo', () => ({
            search: @js($_oldParentName),
            selectedId: @js($_oldParentId),
            open: false,
            tasks: @js($_parentsForCombo),
            get filtered() {
                const q = this.search.toLowerCase().trim();
                if (!q) return this.tasks.slice(0, 10);
                return this.tasks.filter(t => t.rawName.toLowerCase().includes(q)).slice(0, 10);
            },
            select(task) { this.selectedId = task.id; this.search = task.rawName; this.open = false; },
            clear() { this.selectedId = ''; this.search = ''; this.open = false; },
            onInput() { this.selectedId = ''; this.open = true; },
        }));
    });
    </script>
    @endpush
</x-app-layout>
