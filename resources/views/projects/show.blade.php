<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-100 leading-tight">
                {{ $project->name }}
            </h2>
            <div class="flex gap-2">
                <a href="{{ route('projects.export-template', $project) }}" class="px-4 py-2 bg-gray-700 border border-gray-600 text-gray-100 rounded hover:bg-gray-600">
                    Export as Template
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12 relative" @if($project->user_id === Auth::id() && !$project->is_inbox) x-data="projectEditor({{ $project->id }})" @endif>
        @if($project->background_image)
            <div class="absolute inset-0" style="background-image: url('{{ route('projects.background', $project) }}'); background-attachment: fixed; background-position: center; background-size: cover; background-repeat: no-repeat;"></div>
            <div class="absolute inset-0 bg-black/65"></div>
        @endif
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6 relative">
            @if($project->status === 'archived')
                <div class="bg-amber-950/40 border border-amber-700/60 rounded-lg p-4 flex items-center gap-3">
                    <span class="text-amber-500 text-xl">🗄</span>
                    <div>
                        <p class="text-amber-400 font-semibold">This project is archived</p>
                        <p class="text-amber-600 text-sm mt-0.5">Tasks in this project are hidden from your task lists.</p>
                    </div>
                </div>
            @elseif($project->status === 'done')
                <div class="bg-green-950/40 border border-green-700/60 rounded-lg p-4 flex items-center gap-3">
                    <span class="text-green-500 text-xl">✓</span>
                    <div>
                        <p class="text-green-400 font-semibold">This project is done</p>
                        <p class="text-green-600 text-sm mt-0.5">Tasks in this project are hidden from your task lists.</p>
                    </div>
                </div>
            @endif

            <!-- Project Details -->
            <div class="bg-[#202020] border border-gray-700 overflow-hidden shadow-sm sm:rounded-lg p-6">

                @if($project->user_id === Auth::id() && !$project->is_inbox)
                    <!-- Editable project name -->
                    <div class="mb-4">
                        <span class="text-sm font-medium text-gray-500">Project Name</span>
                        <div @click="startEdit('name')" x-show="!editing.name" class="mt-1 cursor-pointer hover:bg-gray-700 p-2 rounded">
                            <p class="text-lg font-semibold text-gray-100">{{ $project->name }}</p>
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

                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <!-- Status (editable) -->
                        <div>
                            <span class="text-sm font-medium text-gray-500">Status</span>
                            <div @click="startEdit('status')" x-show="!editing.status" class="mt-1 cursor-pointer hover:bg-gray-700 p-2 rounded">
                                <span class="inline-block px-2 py-1 text-xs rounded
                                    @if($project->status === 'done') bg-green-100 text-green-800
                                    @elseif($project->status === 'archived') bg-gray-100 text-gray-800
                                    @else bg-blue-100 text-blue-800 @endif">
                                    {{ ucfirst($project->status) }}
                                </span>
                            </div>
                            <div x-show="editing.status" class="mt-1">
                                <select x-model="fields.status"
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
                            <p class="mt-1 text-gray-300">{{ $project->creator->name }}</p>
                        </div>
                    </div>

                    <!-- Description (editable) -->
                    <div class="mt-4">
                        <span class="text-sm font-medium text-gray-500">Description</span>
                        <div @click="startEdit('description')" x-show="!editing.description" class="mt-1 cursor-pointer hover:bg-gray-700 p-2 rounded min-h-[40px]">
                            <p x-show="fields.description" class="text-gray-300" x-text="fields.description"></p>
                            <p x-show="!fields.description" class="text-gray-400 italic">Click to add description</p>
                        </div>
                        <div x-show="editing.description" class="mt-1">
                            <textarea x-model="fields.description" rows="3"
                                      @keydown.ctrl.enter="saveField('description')"
                                      @keydown.escape="cancelEdit('description')"
                                      class="w-full rounded-md bg-gray-700 border-gray-600 text-gray-100 placeholder-gray-500 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                      placeholder="Add a description..."></textarea>
                            <p class="mt-1 text-xs text-gray-500">Ctrl+Enter to save, Escape to cancel</p>
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

                    <!-- Assignees (editable, creator only) -->
                    <div class="mt-4">
                        <span class="text-sm font-medium text-gray-500">Assigned To</span>
                        <div @click="startEdit('assignee_ids')" x-show="!editing.assignee_ids" class="mt-1 cursor-pointer hover:bg-gray-700 p-2 rounded min-h-[40px]">
                            @if($project->assignees->count() > 0)
                                <div class="space-y-1">
                                    @foreach($project->assignees as $assignee)
                                        <p class="text-sm text-gray-300">{{ $assignee->name }}</p>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-gray-400 italic">Click to add assignees</p>
                            @endif
                        </div>
                        <div x-show="editing.assignee_ids" class="mt-1">
                            <div class="space-y-2 mb-2 max-h-48 overflow-y-auto border border-gray-600 bg-[#101010] rounded p-3">
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

                    <!-- Background Image -->
                    <div class="mt-4" x-data="{ showUpload: false }">
                        <span class="text-sm font-medium text-gray-500">Background Image</span>
                        @if($project->background_image)
                            <div class="mt-1">
                                <div class="mb-2">
                                    <img src="{{ route('projects.background', $project) }}"
                                         alt="Project background"
                                         class="rounded-md border border-gray-600"
                                         style="height: 100px; width: auto;">
                                </div>
                                <div class="flex items-center gap-3">
                                    <button @click="showUpload = !showUpload"
                                            class="text-sm text-gray-400 hover:text-gray-200 underline">
                                        Replace
                                    </button>
                                    <form method="POST" action="{{ route('projects.background.remove', $project) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-sm text-red-400 hover:text-red-300 underline">
                                            Remove
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @else
                            <div class="mt-1">
                                <button @click="showUpload = !showUpload"
                                        class="text-sm text-gray-400 hover:text-gray-200 underline">
                                    Add background image
                                </button>
                            </div>
                        @endif
                        <div x-show="showUpload" x-cloak class="mt-2">
                            <form method="POST" action="{{ route('projects.background.upload', $project) }}"
                                  enctype="multipart/form-data" class="flex items-center gap-2 flex-wrap">
                                @csrf
                                <input type="file" name="background_image" required
                                       accept="image/jpeg,image/png,image/webp,image/gif,image/avif,image/heic,image/heif,.jpg,.jpeg,.png,.webp,.gif,.avif,.heic,.heif"
                                       class="block text-sm text-gray-400 file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-gray-700 file:text-gray-100 hover:file:bg-gray-600 bg-[#101010] border border-gray-600 rounded-md">
                                <button type="submit"
                                        class="px-3 py-1.5 bg-blue-600 text-white text-xs rounded hover:bg-blue-700 whitespace-nowrap">
                                    Upload
                                </button>
                                <button type="button" @click="showUpload = false"
                                        class="px-3 py-1.5 bg-gray-700 text-gray-300 text-xs rounded hover:bg-gray-600">
                                    Cancel
                                </button>
                            </form>
                            <p class="mt-1 text-xs text-gray-500">JPG, PNG, WebP, GIF, AVIF, HEIC &mdash; max 20 MB</p>
                            @error('background_image')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
                        </div>
                    </div>

                @else
                    {{-- Read-only view for non-creators --}}
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <span class="text-sm font-medium text-gray-500">Status</span>
                            <p class="mt-1">
                                <span class="inline-block px-2 py-1 text-xs rounded
                                    @if($project->status === 'done') bg-green-100 text-green-800
                                    @elseif($project->status === 'archived') bg-gray-100 text-gray-800
                                    @else bg-blue-100 text-blue-800 @endif">
                                    {{ ucfirst($project->status) }}
                                </span>
                            </p>
                        </div>
                        <div>
                            <span class="text-sm font-medium text-gray-500">Created By</span>
                            <p class="mt-1 text-gray-300">{{ $project->creator->name }}</p>
                        </div>
                    </div>

                    @if($project->description)
                        <div class="mt-4">
                            <span class="text-sm font-medium text-gray-500">Description</span>
                            <p class="mt-1 text-gray-300">{{ $project->description }}</p>
                        </div>
                    @endif

                    @if($project->assignees->count() > 0)
                        <div class="mt-4">
                            <span class="text-sm font-medium text-gray-500">Assigned To</span>
                            <div class="mt-1 space-y-1">
                                @foreach($project->assignees as $assignee)
                                    <p class="text-sm text-gray-300">{{ $assignee->name }}</p>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endif
            </div>

            <!-- Project Tasks -->
            <div class="bg-[#202020] border border-gray-700 overflow-hidden shadow-sm sm:rounded-lg p-6" x-data="taskFilter()">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-100">Tasks
                        <span class="text-sm text-gray-500 font-normal" x-text="$store.taskCount.ready ? ($store.taskCount.filtered ? 'showing ' + $store.taskCount.visible + ' of ' + $store.taskCount.total : $store.taskCount.total) : ''"></span>
                    </h3>
                    <a href="{{ route('tasks.create') }}?project_id={{ $project->id }}" class="text-sm text-blue-400 hover:underline">
                        Add Task
                    </a>
                </div>
                <div class="mb-4">
                    <input type="text"
                           x-model="query"
                           x-on:input="filterTasks()"
                           x-on:keydown.escape="clearFilter()"
                           placeholder="Filter tasks... (@ tag)"
                           class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-md text-gray-100 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <div x-ref="taskContainer">
                    <x-task-list :tasks="$tasks" />
                </div>
                <div x-show="noResults" x-cloak class="bg-[#202020] p-8 rounded-lg text-center text-gray-400 border border-gray-700">
                    No tasks match your filter.
                </div>

                <x-completed-tasks-section :tasks="$completedTasks" />
            </div>
        </div>
    </div>

    @if($project->user_id === Auth::id() && !$project->is_inbox)
    @push('scripts')
    <script>
        function projectEditor(projectId) {
            return {
                projectId: projectId,
                editing: {},
                fields: {
                    name: @js($project->name),
                    description: @js($project->description ?? ''),
                    status: @js($project->status),
                    assignee_ids: @js($project->assignees->pluck('id')->toArray()),
                },
                original: {},

                init() {
                    this.original = JSON.parse(JSON.stringify(this.fields));
                },

                startEdit(field) {
                    this.editing[field] = true;
                    if (field === 'name') {
                        this.$nextTick(() => {
                            const input = this.$el.querySelector('input[x-model="fields.name"]');
                            if (input) { input.focus(); input.select(); }
                        });
                    }
                    if (field === 'description') {
                        this.$nextTick(() => {
                            const ta = this.$el.querySelector('textarea[x-model="fields.description"]');
                            if (ta) ta.focus();
                        });
                    }
                },

                cancelEdit(field) {
                    this.editing[field] = false;
                    this.fields[field] = JSON.parse(JSON.stringify(this.original[field]));
                },

                async saveField(field) {
                    try {
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

                        const response = await fetch(`/projects/${this.projectId}/update-field`, {
                            method: 'POST',
                            body: formData,
                        });

                        const data = await response.json();

                        if (data.success) {
                            this.original[field] = JSON.parse(JSON.stringify(this.fields[field]));
                            this.editing[field] = false;
                            window.location.reload();
                        } else {
                            alert('Error: ' + (data.message || 'Failed to update'));
                            this.fields[field] = JSON.parse(JSON.stringify(this.original[field]));
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        alert('An error occurred while saving');
                        this.fields[field] = JSON.parse(JSON.stringify(this.original[field]));
                    }
                },
            };
        }
    </script>
    @endpush
    @endif
</x-app-layout>
