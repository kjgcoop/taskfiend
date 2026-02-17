<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-100 leading-tight">
            {{ __('Create Task') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-[#0a1a0a] overflow-hidden shadow-sm sm:rounded-lg border border-gray-700">
                <div class="p-6" x-data="taskCreator(@js($projects), @js($tags))">
                    <form method="POST" action="{{ route('tasks.store') }}" @submit="prepareSubmit">
                        @csrf

                        <div class="mb-4 relative">
                            <label for="name" class="block text-sm font-medium text-gray-300 mb-2">
                                Task Name
                                <span class="text-xs text-gray-500 font-normal">(use #project or @tag to auto-select)</span>
                            </label>
                            <input type="text"
                                   x-model="taskName"
                                   @input="handleInput"
                                   @keydown="handleKeydown($event)"
                                   @blur="hideAutocomplete"
                                   id="name"
                                   x-ref="nameInput"
                                   required
                                   class="w-full rounded-md bg-gray-700 border-gray-600 text-gray-100 placeholder-gray-500 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                   placeholder="e.g., Meeting #work @urgent Monday">

                            <!-- Autocomplete Dropdown -->
                            <div x-show="showAutocomplete"
                                 x-transition
                                 class="absolute z-10 mt-1 w-full bg-gray-700 border border-gray-600 rounded-md shadow-lg max-h-60 overflow-auto">
                                <template x-if="autocompleteType === 'project'">
                                    <div>
                                        <div class="px-3 py-2 text-xs font-semibold text-gray-400 bg-[#0a1a0a] border-b border-gray-600">Projects</div>
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
                                        <div class="px-3 py-2 text-xs font-semibold text-gray-400 bg-[#0a1a0a] border-b border-gray-600">Tags</div>
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

                            <p class="mt-1 text-xs text-gray-500">
                                Natural dates: "Monday", "11/10", "every Tuesday" |
                                Type <code class="bg-gray-700 px-1 rounded text-gray-300">#</code> for projects or
                                <code class="bg-gray-700 px-1 rounded text-gray-300">@</code> for tags
                            </p>
                            @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <!-- Hidden field to submit cleaned task name -->
                        <input type="hidden" name="name" x-model="cleanedTaskName">

                        <div class="mb-4">
                            <label for="description" class="block text-sm font-medium text-gray-300 mb-2">Description</label>
                            <textarea name="description" id="description" rows="3"
                                      class="w-full rounded-md bg-gray-700 border-gray-600 text-gray-100 placeholder-gray-500 shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('description') }}</textarea>
                            @error('description')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div class="mb-4 grid grid-cols-2 gap-4">
                            <div>
                                <label for="date" class="block text-sm font-medium text-gray-300 mb-2">Date (Optional)</label>
                                <input type="date" name="date" id="date"
                                       class="w-full rounded-md bg-gray-700 border-gray-600 text-gray-100 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                       value="{{ old('date', $preselectedDate) }}">
                                <p class="mt-1 text-xs text-gray-500">Leave blank to auto-detect from task name.</p>
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
                            <label for="recurrence_pattern" class="block text-sm font-medium text-gray-300 mb-2">Recurrence (Optional)</label>
                            <input type="text" name="recurrence_pattern" id="recurrence_pattern"
                                   class="w-full rounded-md bg-gray-700 border-gray-600 text-gray-100 placeholder-gray-500 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                   value="{{ old('recurrence_pattern') }}"
                                   placeholder="e.g., daily, every Monday, weekdays">
                            <p class="mt-1 text-xs text-gray-500">Leave blank to auto-detect from task name. Use "every!" for floating (e.g., "every! 3 weeks").</p>
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
                        <div class="mb-4">
                            <label for="parent_id" class="block text-sm font-medium text-gray-300 mb-2">
                                Parent Task (Optional - create as subtask)
                            </label>
                            <select name="parent_id" id="parent_id"
                                    class="w-full rounded-md bg-gray-700 border-gray-600 text-gray-100 placeholder-gray-500 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">None (Top-level task)</option>
                                @foreach($availableParents as $parentOption)
                                    <option value="{{ $parentOption->id }}">
                                        {{ str_repeat('→ ', $parentOption->getDepth()) }}{{ $parentOption->name }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-gray-500">
                                Select a parent task to create this as a subtask. Subtasks inherit permissions from their parent.
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
                                Create Task
                            </button>
                            <a href="{{ route('today') }}" class="text-sm text-gray-400 hover:text-gray-100">
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
        function taskCreator(projects, tags) {
            return {
                projects: projects,
                tags: tags,
                taskName: @js(old('name', '')),
                selectedProjectId: @js(old('project_id', $preselectedProjectId ?? '')),
                selectedTagIds: @js(old('tag_ids', [])),

                // Autocomplete state
                showAutocomplete: false,
                autocompleteType: null,
                autocompleteIndex: 0,
                autocompleteQuery: '',
                confirmedTagSlugs: [],

                get cleanedTaskName() {
                    let name = this.taskName;
                    // Only remove @slugs that were actually selected as tags
                    for (const slug of this.confirmedTagSlugs) {
                        name = name.replace(new RegExp('@' + slug + '(?=\\s|$)', 'g'), '');
                    }
                    name = name.replace(/#\w+/g, '');
                    return name.trim().replace(/\s+/g, ' ');
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
                    const input = this.taskName;
                    const cursorPos = event.target.selectionStart;

                    const beforeCursor = input.substring(0, cursorPos);

                    // Check if we're typing a project (#) or tag (@)
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
                    if (!this.showAutocomplete) return;

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

                    // Replace the incomplete word with the selected name
                    let newBefore;
                    let slug = name.toLowerCase().replace(/[^a-z0-9]/g, '');

                    if (this.autocompleteType === 'project') {
                        newBefore = beforeCursor.replace(/#\w*$/, '#' + slug + ' ');

                        // Auto-select the project in the dropdown
                        const project = this.projects.find(p =>
                            p.name.toLowerCase().replace(/[^a-z0-9]/g, '') === slug
                        );
                        if (project) {
                            this.selectedProjectId = project.id;
                        }
                    } else {
                        newBefore = beforeCursor.replace(/@\w*$/, '@' + slug + ' ');

                        // Auto-select the tag checkbox
                        const tag = this.tags.find(t =>
                            t.tag_name.toLowerCase().replace(/[^a-z0-9]/g, '') === slug
                        );
                        if (tag && !this.selectedTagIds.includes(tag.id)) {
                            this.selectedTagIds.push(tag.id);
                        }
                        this.confirmedTagSlugs.push(slug);
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
                    // The form will submit with the cleaned task name via the hidden field
                    // Project and tags are already selected in their respective fields
                }
            };
        }
    </script>
    @endpush
</x-app-layout>
