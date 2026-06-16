<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2 w-full" x-data="dropdown">
            {{-- Inline-editable tag name + count badge --}}
            <div x-data="tagHeaderEditor"
                 data-tag-id="{{ $tag->id }}"
                 data-tag-name="{{ $tag->tag_name }}"
                 data-tag-color="{{ $tag->color }}"
                 class="flex items-center gap-2 min-w-0">
                <h2 x-show="!editing"
                    @click="startEdit()"
                    class="font-semibold text-xl leading-tight truncate cursor-pointer hover:opacity-80 transition-opacity"
                    style="color: {{ $tag->color }}"
                    x-text="name">
                </h2>
                <input x-show="editing" x-cloak x-ref="nameInput"
                       x-model="name"
                       @blur="save()"
                       @keydown.enter.prevent="save()"
                       @keydown.escape.prevent="cancel()"
                       class="font-semibold text-xl bg-transparent border-b border-gray-400 focus:outline-none focus:border-blue-400 min-w-0 w-52"
                       :style="'color: ' + color" />
                <x-task-count-badge :count="$tasks->count()" :breakdown="$breakdown" />
            </div>

            {{-- Spacer --}}
            <div class="flex-1"></div>

            {{-- ID + copy link --}}
            <span class="text-sm text-gray-500 shrink-0">#{{ $tag->id }}</span>
            <span x-data="copyButton" class="shrink-0">
                <button @click="copy('[tag:{{ $tag->id }}]')"
                        title="Copy tag reference [tag:{{ $tag->id }}]"
                        class="text-gray-500 hover:text-gray-300 transition-colors">
                    <span x-show="!copied">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                        </svg>
                    </span>
                    <span x-show="copied" x-cloak class="text-xs text-green-400">Copied!</span>
                </button>
            </span>

            {{-- Three-dot menu --}}
            <div class="relative shrink-0" @click.outside="open = false">
                <button @click="open = !open"
                        class="p-2 text-gray-400 hover:text-gray-100 hover:bg-gray-700 rounded transition-colors"
                        title="More options">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M10 6a2 2 0 110-4 2 2 0 010 4zm0 6a2 2 0 110-4 2 2 0 010 4zm0 6a2 2 0 110-4 2 2 0 010 4z" />
                    </svg>
                </button>
                <div x-show="open" x-cloak
                     class="absolute right-0 mt-1 w-40 bg-gray-800 border border-gray-600 rounded shadow-lg z-10">
                    <button @click="openDetails('open-tag-details')"
                            class="w-full text-left px-4 py-2 text-gray-200 hover:bg-gray-700">
                        Details
                    </button>
                    <a href="{{ route('tags.export-markdown', $tag) }}"
                       class="block px-4 py-2 text-gray-200 hover:bg-gray-700">
                        Export .md
                    </a>
                </div>
            </div>
        </div>
    </x-slot>

    <div class="py-12" x-data="tagEditor" data-tag-id="{{ $tag->id }}"
         @open-tag-details.window="showDetails = true">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Details Modal --}}
            <div x-show="showDetails" x-cloak
                 class="fixed inset-0 z-50 flex items-center justify-center bg-black/60"
                 @keydown.escape.window="showDetails = false">
                <div class="bg-gray-800 border border-gray-600 rounded-lg p-6 w-full max-w-md shadow-xl overflow-y-auto max-h-[90vh]"
                     @click.stop>
                    <div class="flex justify-between items-center mb-5">
                        <h4 class="text-gray-100 font-semibold text-lg">Tag Details</h4>
                        <button @click="showDetails = false" class="text-gray-400 hover:text-gray-100">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    {{-- Tag Name --}}
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-400 mb-1">Tag Name</label>
                        <div class="flex gap-2">
                            <input type="text" x-model="fields.tag_name"
                                   @keydown.enter="saveField('tag_name')"
                                   class="flex-1 rounded-md bg-gray-700 border-gray-600 text-gray-100 placeholder-gray-500 shadow-sm focus:border-blue-500 focus:ring-blue-500 px-3 py-2 text-sm">
                            <button @click="saveField('tag_name')"
                                    class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                                Save
                            </button>
                        </div>
                    </div>

                    {{-- Color --}}
                    <div class="mb-5">
                        <label class="block text-sm font-medium text-gray-400 mb-2">Color</label>
                        <div class="flex flex-wrap gap-2 mb-3">
                            @foreach(['#EF4444','#F97316','#F59E0B','#EAB308','#84CC16','#22C55E','#10B981','#14B8A6','#06B6D4','#3B82F6','#6366F1','#8B5CF6','#A855F7','#EC4899','#F43F5E','#78716C','#6B7280','#64748B','#0EA5E9','#0D9488','#15803D','#B45309','#C2410C','#9F1239'] as $c)
                            <button type="button"
                                @click="fields.color = '{{ $c }}'"
                                :class="fields.color === '{{ $c }}' ? 'ring-2 ring-offset-2 ring-offset-gray-800 ring-white scale-110' : 'hover:scale-110'"
                                class="w-7 h-7 rounded-full transition-transform"
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
                                @input="$event.target.value.match(/^[0-9a-fA-F]{6}$/) && (fields.color = '#' + $event.target.value)"
                                class="w-24 rounded-md bg-gray-700 border-gray-600 text-gray-100 text-sm px-2 py-1 focus:border-blue-500 focus:ring-blue-500"
                                placeholder="3B82F6">
                        </div>
                        <button @click="saveField('color')"
                                class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                            Save Color
                        </button>
                    </div>

                    <div class="flex justify-between items-center pt-4 border-t border-gray-700">
                        <form method="POST" action="{{ route('tags.destroy', $tag) }}"
                              x-data @submit.prevent="confirm('Are you sure you want to delete this tag?') && $el.submit()">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-sm text-red-500 hover:text-red-400 hover:underline">
                                Delete Tag
                            </button>
                        </form>
                        <button @click="showDetails = false"
                                class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-gray-300 rounded text-sm">
                            Close
                        </button>
                    </div>
                </div>
            </div>

            <!-- Tagged Tasks -->
            <div class="bg-[#202020] border border-gray-700 shadow-sm sm:rounded-lg p-6"
                 x-data="taskFilter"
                 data-projects="{{ json_encode($projects) }}"
                 data-tags="{{ json_encode($allTags) }}"
                 data-users="{{ json_encode($users) }}"
                 data-locations="{{ json_encode($locations) }}">
                <div class="flex items-center justify-between mb-4">
                    <button type="button"
                            @click="showIncomplete = !showIncomplete"
                            title="Toggle task list"
                            class="text-gray-500 hover:text-gray-300 transition-colors flex-shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg"
                             class="h-4 w-4 transition-transform duration-150"
                             :class="showIncomplete ? 'rotate-90' : ''"
                             fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                    <div class="flex items-center gap-2">
                        <select id="sort-select" @change="sortBy($event.target.value)"
                                class="text-sm bg-gray-700 border border-gray-600 rounded px-2 py-1 text-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="date" {{ $sort === 'date' ? 'selected' : '' }}>Date & Time</option>
                            <option value="created" {{ $sort === 'created' ? 'selected' : '' }}>Date Added</option>
                            <option value="name" {{ $sort === 'name' ? 'selected' : '' }}>Name</option>
                            <option value="duration" {{ $sort === 'duration' ? 'selected' : '' }}>Duration</option>
                            <option value="location" {{ $sort === 'location' ? 'selected' : '' }}>Location</option>
                            <option value="custom" {{ $sort === 'custom' ? 'selected' : '' }}>Custom Sort</option>
                        </select>
                        @if($sort !== 'custom')
                        <button @click="toggleSortReversed()"
                                title="{{ request()->boolean('reversed') ? 'Reversed — click to restore' : 'Reverse sort order' }}"
                                class="p-1 rounded transition-colors {{ request()->boolean('reversed') ? 'text-blue-400 hover:text-blue-300' : 'text-gray-500 hover:text-gray-300' }}">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7 16V4M3 8l4-4 4 4M17 8v12M13 16l4 4 4-4" />
                            </svg>
                        </button>
                        @endif
                    </div>
                </div>
                <div x-show="showIncomplete">
                    <x-task-input-bar :tag-id="$tag->id" filter-placeholder="Filter tasks... (# project)" />
                    <div x-ref="taskContainer">
                        <x-task-list :tasks="$tasks" :sortable="$sort === 'custom'" :reorder-url="route('tags.reorderTasks', $tag)" />
                    </div>
                    <div x-show="noResults" x-cloak class="bg-[#202020] p-8 rounded-lg text-center text-gray-400 border border-gray-700">
                        No tasks match your filter.
                    </div>
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
                    label="Archived tasks"
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
    <script nonce="{{ csp_nonce() }}">
        document.addEventListener('alpine:init', () => {
            Alpine.data('tagHeaderEditor', function() {
                return {
                tagId: 0,
                name: '',
                color: '',
                original: '',
                editing: false,

                init() {
                    this.tagId = parseInt(this.$el.dataset.tagId);
                    this.name = this.$el.dataset.tagName || '';
                    this.color = this.$el.dataset.tagColor || '';
                    this.original = this.name;
                },

                startEdit() {
                    this.original = this.name;
                    this.editing = true;
                    this.$nextTick(() => {
                        if (this.$refs.nameInput) {
                            this.$refs.nameInput.focus();
                            this.$refs.nameInput.select();
                        }
                    });
                },

                cancel() {
                    this.name = this.original;
                    this.editing = false;
                },

                async save() {
                    if (this.name.trim() === this.original.trim()) {
                        this.editing = false;
                        return;
                    }
                    const formData = new FormData();
                    formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);
                    formData.append('field', 'tag_name');
                    formData.append('value', this.name.trim());
                    try {
                        const resp = await fetch(`/tags/${this.tagId}/update-field`, {
                            method: 'POST',
                            body: formData,
                        });
                        const data = await resp.json();
                        if (data.success) {
                            this.original = this.name.trim();
                            this.editing = false;
                        } else {
                            alert(data.message || 'Failed to save');
                            this.name = this.original;
                            this.editing = false;
                        }
                    } catch (e) {
                        alert('An error occurred while saving');
                        this.name = this.original;
                        this.editing = false;
                    }
                },
                };
            });
        });

        document.addEventListener('alpine:init', () => {
            Alpine.data('tagEditor', function() {
                return {
                tagId: 0,
                showDetails: false,
                editing: {},
                fields: {
                    tag_name: @js($tag->tag_name),
                    color: @js($tag->color),
                },
                original: {},

                init() {
                    this.tagId = parseInt(this.$el.dataset.tagId) || 0;
                    this.original = JSON.parse(JSON.stringify(this.fields));
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
            });
        });
    </script>
    @endpush
</x-app-layout>
