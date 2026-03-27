<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-100 leading-tight" style="color: {{ $tag->color }}">
                {{ $tag->tag_name }}
            </h2>
        </div>
    </x-slot>

    <div class="py-12" x-data="tagEditor({{ $tag->id }})">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <!-- Tag Details -->
            <div class="bg-[#202020] border border-gray-700 overflow-hidden shadow-sm sm:rounded-lg p-6">
                <!-- Tag Name -->
                <div class="mb-4">
                    <span class="text-sm font-medium text-gray-500">Tag Name</span>
                    <div @click="startEdit('tag_name')" x-show="!editing.tag_name" class="mt-1 cursor-pointer hover:bg-gray-700 p-2 rounded flex items-center gap-3">
                        <div class="w-6 h-6 rounded-full flex-shrink-0" :style="'background-color:' + fields.color"></div>
                        <p class="text-lg font-semibold text-gray-100" x-text="fields.tag_name"></p>
                    </div>
                    <div x-show="editing.tag_name" class="mt-1">
                        <input type="text" x-model="fields.tag_name"
                               @keydown.enter="saveField('tag_name')"
                               @keydown.escape="cancelEdit('tag_name')"
                               class="w-full rounded-md bg-gray-700 border-gray-600 text-gray-100 placeholder-gray-500 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <div class="flex gap-2 mt-2">
                            <button @click="saveField('tag_name')"
                                    class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                                Save
                            </button>
                            <button @click="cancelEdit('tag_name')"
                                    class="px-3 py-1 bg-gray-700 text-gray-300 text-sm rounded hover:bg-gray-600">
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Color -->
                <div class="mt-4">
                    <span class="text-sm font-medium text-gray-500">Color</span>
                    <div @click="startEdit('color')" x-show="!editing.color" class="mt-1 cursor-pointer hover:bg-gray-700 p-2 rounded flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg" :style="'background-color:' + fields.color"></div>
                        <p class="text-sm text-gray-400" x-text="fields.color"></p>
                    </div>
                    <div x-show="editing.color" class="mt-1">
                        <div class="flex flex-wrap gap-2 mb-3">
                            @foreach(['#EF4444','#F97316','#F59E0B','#EAB308','#84CC16','#22C55E','#10B981','#14B8A6','#06B6D4','#3B82F6','#6366F1','#8B5CF6','#A855F7','#EC4899','#F43F5E','#78716C','#6B7280','#64748B','#0EA5E9','#0D9488','#15803D','#B45309','#C2410C','#9F1239'] as $c)
                            <button type="button"
                                @click="fields.color = '{{ $c }}'"
                                :class="fields.color === '{{ $c }}' ? 'ring-2 ring-offset-2 ring-offset-[#202020] ring-white scale-110' : 'hover:scale-110'"
                                class="w-8 h-8 rounded-full transition-transform"
                                style="background-color: {{ $c }};"
                                title="{{ $c }}">
                            </button>
                            @endforeach
                        </div>
                        <div class="flex items-center gap-2 mb-3">
                            <div class="w-5 h-5 rounded-full border border-gray-600 flex-shrink-0" :style="'background-color:' + fields.color"></div>
                            <span class="text-gray-400 text-sm">#</span>
                            <input type="text" maxlength="6"
                                :value="fields.color.replace('#', '')"
                                @input="if ($event.target.value.match(/^[0-9a-fA-F]{6}$/)) fields.color = '#' + $event.target.value"
                                class="w-24 rounded-md bg-gray-700 border-gray-600 text-gray-100 text-sm px-2 py-1 focus:border-blue-500 focus:ring-blue-500"
                                placeholder="3B82F6">
                        </div>
                        <div class="flex gap-2">
                            <button @click="saveField('color')"
                                    class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                                Save
                            </button>
                            <button @click="cancelEdit('color')"
                                    class="px-3 py-1 bg-gray-700 text-gray-300 text-sm rounded hover:bg-gray-600">
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Delete -->
                <div class="mt-6 pt-4 border-t border-gray-700">
                    <form method="POST" action="{{ route('tags.destroy', $tag) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-sm text-red-500 hover:text-red-400 hover:underline"
                                onclick="return confirm('Are you sure you want to delete this tag?')">
                            Delete Tag
                        </button>
                    </form>
                </div>
            </div>

            <!-- Tagged Tasks -->
            <div class="bg-[#202020] border border-gray-700 overflow-hidden shadow-sm sm:rounded-lg p-6" x-data="taskFilter(@js($projects), @js($allTags))">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-100">Tagged Tasks
                        <span class="text-sm text-gray-500 font-normal" x-text="$store.taskCount.ready ? ($store.taskCount.filtered ? 'showing ' + $store.taskCount.visible + ' of ' + $store.taskCount.total : $store.taskCount.total) : ''"></span>
                    </h3>
                    <select onchange="(function(v){const p=new URLSearchParams(window.location.search);p.set('sort',v);window.location.href=window.location.pathname+'?'+p.toString()})(this.value)"
                            class="text-sm bg-gray-700 border border-gray-600 rounded px-2 py-1 text-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="date" {{ $sort === 'date' ? 'selected' : '' }}>Date & Time</option>
                        <option value="created" {{ $sort === 'created' ? 'selected' : '' }}>Date Added</option>
                        <option value="name" {{ $sort === 'name' ? 'selected' : '' }}>Name (A–Z)</option>
                    </select>
                </div>
                <x-task-input-bar :tag-id="$tag->id" filter-placeholder="Filter tasks... (# project)" />
                <div x-ref="taskContainer">
                    <x-task-list :tasks="$tasks" />
                </div>
                <div x-show="noResults" x-cloak class="bg-[#202020] p-8 rounded-lg text-center text-gray-400 border border-gray-700">
                    No tasks match your filter.
                </div>
                <x-completed-tasks-section
                    :tasks="$completedTasks"
                    :total-count="$completedTasksTotal"
                    :has-more="$completedTasksHasMore"
                    :next-page="2"
                    :ajax-url="$completedTasksHasMore ? route('tags.completedTasks', $tag) : null"
                />
                <x-completed-tasks-section
                    :tasks="$archivedTasks"
                    label="Show archived tasks"
                    :read-only="true"
                    :show-as-archived="true"
                    :total-count="$archivedTasksTotal"
                    :has-more="$archivedTasksHasMore"
                    :next-page="2"
                    :ajax-url="$archivedTasksHasMore ? route('tags.archivedTasks', $tag) : null"
                />
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function tagEditor(tagId) {
            return {
                tagId: tagId,
                editing: {},
                fields: {
                    tag_name: @js($tag->tag_name),
                    color: @js($tag->color),
                },
                original: {},

                init() {
                    this.original = JSON.parse(JSON.stringify(this.fields));
                },

                startEdit(field) {
                    this.editing[field] = true;
                    if (field === 'tag_name') {
                        this.$nextTick(() => {
                            const input = this.$el.querySelector('input[x-model="fields.tag_name"]');
                            if (input) { input.focus(); input.select(); }
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
                        formData.append('value', this.fields[field]);

                        const response = await fetch(`/tags/${this.tagId}/update-field`, {
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
</x-app-layout>
