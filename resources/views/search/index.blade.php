<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-100 leading-tight">
            {{ __('Search Tasks') }}
            @if($tasksTotal > 0)
                <x-task-count-badge :count="$tasksTotal" :breakdown="$breakdown" />
            @endif
        </h2>
    </x-slot>

    @php
        $hasSearchParams = request()->hasAny(['q', 'tag_ids', 'project_id', 'location', 'has_location', 'date_from', 'date_to', 'has_date', 'assignee_id', 'creator_id', 'show_incomplete', 'show_done', 'show_archived', 'show_archived_projects', 'sort']);
    @endphp
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <!-- Search Form -->
            <div class="bg-[#202020] border border-gray-700 overflow-hidden shadow-sm sm:rounded-lg p-6" x-data="searchFilter(@js($projects), @js($tags), {{ $hasSearchParams ? 'false' : 'true' }})">
                <form method="GET" action="{{ route('search') }}" @submit="prepareSubmit">
                    <!-- Main Search Input -->
                    <div class="mb-4 relative">
                        <label for="search" class="block text-sm font-medium text-gray-300 mb-2">
                            Search
                            <span class="text-xs text-gray-500 font-normal">(use #project or @tag to filter)</span>
                        </label>
                        <input type="text"
                               x-model="searchInput"
                               @input="handleInput"
                               @keydown="handleKeydown($event)"
                               @blur="hideAutocomplete"
                               id="search"
                               x-ref="searchInput"
                               class="w-full rounded-md bg-gray-700 border-gray-600 text-gray-100 placeholder-gray-500 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                               placeholder="e.g., meeting #work @urgent">

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
                                             :class="{ 'bg-gray-600': autocompleteIndex === index + 1 }">
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
                                    <div x-show="filteredTags.length === 0" class="px-3 py-2 text-sm text-gray-500 italic">
                                        No matching tags
                                    </div>
                                </div>
                            </template>
                        </div>

                        <p class="mt-1 text-xs text-gray-500">
                            Type <code class="bg-gray-700 px-1 rounded">#{'}#{'}</code> for projects
                            or <code class="bg-gray-700 px-1 rounded">@</code> for tags - autocomplete will appear!
                        </p>
                    </div>

                    <!-- Hidden form fields -->
                    <input type="hidden" name="q" x-model="queryText">
                    <input type="hidden" name="project_id" x-model="selectedProjectId">
                    <template x-for="tagId in selectedTagIds" :key="tagId">
                        <input type="hidden" name="tag_ids[]" :value="tagId">
                    </template>

                    <!-- Filters toggle -->
                    <div class="mb-4">
                        <button type="button"
                                @click="expanded = !expanded"
                                class="inline-flex items-center gap-1 text-sm text-gray-400 hover:text-gray-200 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg"
                                 class="h-4 w-4 transition-transform duration-200"
                                 :class="expanded ? 'rotate-180' : ''"
                                 fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                            <span x-text="expanded ? 'Hide filters' : 'Show filters'"></span>
                        </button>
                    </div>

                    <!-- Collapsible filters -->
                    <div x-show="expanded" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">

                    <!-- Project Filter -->
                    <div class="mb-4">
                        <label for="project_filter" class="block text-sm font-medium text-gray-300 mb-2">Project</label>
                        <select x-model="selectedProjectId"
                                @change="updateSearchFromFilters"
                                id="project_filter"
                                class="w-full rounded-md bg-gray-700 border-gray-600 text-gray-100 placeholder-gray-500 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="none">No project filter (search all)</option>
                            <template x-for="project in projects" :key="project.id">
                                <option :value="project.id" x-text="project.name"></option>
                            </template>
                        </select>
                    </div>

                    <!-- Tag Cloud -->
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-300 mb-2">Tags</label>
                        <div class="flex flex-wrap gap-2">
                            <template x-for="tag in tags" :key="tag.id">
                                <label class="inline-flex items-center cursor-pointer">
                                    <input type="checkbox"
                                           :value="tag.id"
                                           @change="toggleTag(tag.id)"
                                           :checked="selectedTagIds.includes(tag.id)"
                                           class="rounded border-gray-600 bg-gray-700 text-blue-600 focus:ring-blue-500">
                                    <span class="ml-2 text-sm" :style="'color: ' + tag.color" x-text="tag.tag_name"></span>
                                </label>
                            </template>
                            <p x-show="tags.length === 0" class="text-sm text-gray-500">No tags available.</p>
                        </div>
                    </div>

                    <!-- Date Filters -->
                    <div class="mb-4 grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label for="date_from" class="block text-sm font-medium text-gray-300 mb-2">Date from</label>
                            <input type="date" name="date_from" id="date_from"
                                   value="{{ request('date_from') }}"
                                   class="w-full rounded-md bg-gray-700 border-gray-600 text-gray-100 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label for="date_to" class="block text-sm font-medium text-gray-300 mb-2">Date to</label>
                            <input type="date" name="date_to" id="date_to"
                                   value="{{ request('date_to') }}"
                                   class="w-full rounded-md bg-gray-700 border-gray-600 text-gray-100 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div class="flex items-end pb-1">
                            <label class="inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="has_date" value="1" {{ request('has_date') ? 'checked' : '' }}
                                       class="rounded border-gray-600 bg-gray-700 text-blue-600 focus:ring-blue-500">
                                <span class="ml-2 text-sm text-gray-300">Has a date</span>
                            </label>
                        </div>
                    </div>

                    <!-- Person Filters -->
                    <div class="mb-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="assignee_id" class="block text-sm font-medium text-gray-300 mb-2">Assigned to</label>
                            <select name="assignee_id" id="assignee_id"
                                    class="w-full rounded-md bg-gray-700 border-gray-600 text-gray-100 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Anyone</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}" {{ request('assignee_id') == $user->id ? 'selected' : '' }}>
                                        {{ $user->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="creator_id" class="block text-sm font-medium text-gray-300 mb-2">Created by</label>
                            <select name="creator_id" id="creator_id"
                                    class="w-full rounded-md bg-gray-700 border-gray-600 text-gray-100 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Anyone</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}" {{ request('creator_id') == $user->id ? 'selected' : '' }}>
                                        {{ $user->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <!-- Location Filter -->
                    <div class="mb-4 grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="sm:col-span-2">
                            <label for="location" class="block text-sm font-medium text-gray-300 mb-2">Location</label>
                            <input type="text" name="location" id="location"
                                   value="{{ request('location') }}"
                                   placeholder="e.g., Home, Zoom, Conference Room B"
                                   class="w-full rounded-md bg-gray-700 border-gray-600 text-gray-100 placeholder-gray-500 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div class="flex items-end pb-1">
                            <label class="inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="has_location" value="1" {{ request('has_location') ? 'checked' : '' }}
                                       class="rounded border-gray-600 bg-gray-700 text-blue-600 focus:ring-blue-500">
                                <span class="ml-2 text-sm text-gray-300">Has a location</span>
                            </label>
                        </div>
                    </div>

                    <!-- Status Filters -->
                    @php
                        $hasSearchParams = request()->hasAny(['q', 'tag_ids', 'project_id', 'location', 'has_location', 'date_from', 'date_to', 'has_date', 'assignee_id', 'creator_id', 'show_incomplete', 'show_done', 'show_archived', 'show_archived_projects', 'sort']);
                        $defaultIncomplete = $hasSearchParams ? request()->boolean('show_incomplete') : true;
                    @endphp
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-300 mb-2">Status</label>
                        <div class="flex items-center gap-6 flex-wrap">
                            <label class="inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="show_incomplete" value="1" {{ $defaultIncomplete ? 'checked' : '' }}
                                       class="rounded border-gray-600 bg-gray-700 text-blue-600 focus:ring-blue-500">
                                <span class="ml-2 text-sm text-gray-300">Incomplete</span>
                            </label>
                            <label class="inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="show_done" value="1" {{ request('show_done') ? 'checked' : '' }}
                                       class="rounded border-gray-600 bg-gray-700 text-blue-600 focus:ring-blue-500">
                                <span class="ml-2 text-sm text-gray-300">Done</span>
                            </label>
                            <label class="inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="show_archived" value="1" {{ request('show_archived') ? 'checked' : '' }}
                                       class="rounded border-gray-600 bg-gray-700 text-blue-600 focus:ring-blue-500">
                                <span class="ml-2 text-sm text-gray-300">Archived</span>
                            </label>
                            <span class="text-gray-600 select-none">|</span>
                            <label class="inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="show_archived_projects" value="1" {{ request('show_archived_projects') ? 'checked' : '' }}
                                       class="rounded border-gray-600 bg-gray-700 text-blue-600 focus:ring-blue-500">
                                <span class="ml-2 text-sm text-gray-400">Include completed projects</span>
                            </label>
                        </div>
                    </div>

                    <div class="flex items-center gap-4 flex-wrap">
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700">
                            Search
                        </button>
                        <a href="{{ route('search') }}" class="text-sm text-gray-400 hover:text-gray-300">
                            Clear
                        </a>
                        <div class="ml-auto flex items-center gap-2">
                            <label for="sort" class="text-sm text-gray-400">Sort by</label>
                            <select name="sort" id="sort" class="rounded-md bg-gray-700 border-gray-600 text-gray-100 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="date_asc" {{ request('sort', 'date_asc') === 'date_asc' ? 'selected' : '' }}>Date (oldest first)</option>
                                <option value="date_desc" {{ request('sort') === 'date_desc' ? 'selected' : '' }}>Date (newest first)</option>
                                <option value="name_asc" {{ request('sort') === 'name_asc' ? 'selected' : '' }}>Name (A–Z)</option>
                                <option value="name_desc" {{ request('sort') === 'name_desc' ? 'selected' : '' }}>Name (Z–A)</option>
                                <option value="created_desc" {{ request('sort') === 'created_desc' ? 'selected' : '' }}>Recently created</option>
                                <option value="location_asc" {{ request('sort') === 'location_asc' ? 'selected' : '' }}>Location (A–Z)</option>
                                <option value="location_desc" {{ request('sort') === 'location_desc' ? 'selected' : '' }}>Location (Z–A)</option>
                            </select>
                        </div>
                    </div>

                    </div>{{-- end collapsible filters --}}
                </form>
            </div>

            <!-- Search Results -->
            @if(request()->hasAny(['q', 'tag_ids', 'project_id', 'date_from', 'date_to', 'has_date', 'assignee_id', 'creator_id', 'show_incomplete', 'show_done', 'show_archived', 'show_archived_projects']))
                @php
                    $multipleStatuses = (request('show_incomplete') ? 1 : 0) + (request('show_done') ? 1 : 0) + (request('show_archived') ? 1 : 0) > 1;
                    $totalFound = $tasksTotal + $completedTasksTotal + $archivedTasksTotal;
                    // Base URL for load-more AJAX calls — all current search params minus export/page/status
                    $moreBaseUrl = route('search.more') . '?' . http_build_query(request()->except(['export', 'page', 'status']));
                @endphp
                <div class="bg-[#202020] border border-gray-700 overflow-hidden shadow-sm sm:rounded-lg p-6" x-data="taskFilter(@js($projects), @js($tags))">
                    @if($tasksTotal > 0 || $completedTasksTotal > 0 || $archivedTasksTotal > 0)
                    <div class="flex justify-end mb-4">
                        <a href="{{ request()->fullUrlWithQuery(['export' => 'markdown']) }}" class="px-3 py-1.5 bg-gray-700 border border-gray-600 text-xs text-gray-100 rounded hover:bg-gray-600">
                            Export .md
                        </a>
                    </div>
                    @endif
                    {{-- Filter bar / bulk-edit header --}}
                    <div class="mb-4">
                        {{-- Normal filter input (hidden in bulk mode) --}}
                        <div x-show="!$store.bulkEdit.active" class="flex gap-2 items-center">
                            <input type="text"
                                   x-model="query"
                                   x-on:input="filterTasks()"
                                   x-on:keydown.escape="clearFilter()"
                                   placeholder="Filter results... (# project, @ tag)"
                                   class="flex-1 px-4 py-2 bg-gray-700 border border-gray-600 rounded-md text-gray-100 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <button @click="$store.bulkEdit.toggle()"
                                    title="Bulk edit mode"
                                    :class="$store.bulkEdit.active ? 'text-blue-400 bg-gray-700' : 'text-gray-500 hover:text-gray-300'"
                                    class="flex-shrink-0 p-2 rounded-md hover:bg-gray-700 transition">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                                </svg>
                            </button>
                        </div>
                        {{-- Bulk edit header (shown in bulk mode) --}}
                        <div x-show="$store.bulkEdit.active" x-cloak class="flex gap-3 items-center">
                            <button @click="$store.bulkEdit.toggle()"
                                    title="Exit bulk edit"
                                    class="flex-shrink-0 p-2 text-blue-400 bg-gray-700 rounded-md hover:bg-gray-600 transition">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                                </svg>
                            </button>
                            <span class="text-sm font-medium text-blue-400">Bulk edit</span>
                            <span class="text-sm text-gray-400">
                                <span x-text="$store.bulkEdit.count"></span>
                                <span x-text="$store.bulkEdit.count === 1 ? 'task' : 'tasks'"></span>
                                selected
                            </span>
                            <button @click="$store.bulkEdit.selectAllVisible()"
                                    class="text-xs text-blue-400 hover:text-blue-300 underline">
                                Select all visible
                            </button>
                            <button @click="$store.bulkEdit.deselectAll()"
                                    x-show="$store.bulkEdit.count > 0"
                                    class="text-xs text-gray-500 hover:text-gray-300 underline">
                                Deselect all
                            </button>
                        </div>
                    </div>
                    <div x-ref="taskContainer">
                        @if($tasksTotal > 0)
                            <div x-data="searchSectionLoader({{ $tasksHasMore ? 'true' : 'false' }}, {{ json_encode($moreBaseUrl . '&status=incomplete') }})">
                                @if($multipleStatuses)
                                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">
                                        Incomplete
                                        @if($tasksTotal > $tasks->count())
                                            <span class="font-normal normal-case text-gray-600">({{ $tasksTotal }} total)</span>
                                        @endif
                                    </p>
                                @endif
                                <div x-ref="list"><x-task-list :tasks="$tasks" /></div>
                                <div class="mt-4 text-center" x-show="hasMore">
                                    <button @click="loadMore()" :disabled="loading"
                                            class="px-5 py-2 bg-gray-700 hover:bg-gray-600 text-gray-200 text-sm rounded-md disabled:opacity-50 disabled:cursor-wait">
                                        <span x-show="!loading">Load more</span>
                                        <span x-show="loading">Loading…</span>
                                    </button>
                                </div>
                            </div>
                        @endif

                        @if($completedTasksTotal > 0)
                            <div class="{{ $tasksTotal > 0 ? 'mt-6 border-t border-gray-700 pt-4' : '' }}"
                                 x-data="searchSectionLoader({{ $completedTasksHasMore ? 'true' : 'false' }}, {{ json_encode($moreBaseUrl . '&status=done') }})">
                                @if($multipleStatuses)
                                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">
                                        Done
                                        @if($completedTasksTotal > $completedTasks->count())
                                            <span class="font-normal normal-case text-gray-600">({{ $completedTasksTotal }} total)</span>
                                        @endif
                                    </p>
                                @endif
                                <div x-ref="list"><x-task-list :tasks="$completedTasks" /></div>
                                <div class="mt-4 text-center" x-show="hasMore">
                                    <button @click="loadMore()" :disabled="loading"
                                            class="px-5 py-2 bg-gray-700 hover:bg-gray-600 text-gray-200 text-sm rounded-md disabled:opacity-50 disabled:cursor-wait">
                                        <span x-show="!loading">Load more</span>
                                        <span x-show="loading">Loading…</span>
                                    </button>
                                </div>
                            </div>
                        @endif

                        @if($archivedTasksTotal > 0)
                            <div class="{{ ($tasksTotal > 0 || $completedTasksTotal > 0) ? 'mt-6 border-t border-gray-700 pt-4' : '' }}"
                                 x-data="searchSectionLoader({{ $archivedTasksHasMore ? 'true' : 'false' }}, {{ json_encode($moreBaseUrl . '&status=archived') }})">
                                @if($multipleStatuses)
                                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">
                                        Archived
                                        @if($archivedTasksTotal > $archivedTasks->count())
                                            <span class="font-normal normal-case text-gray-600">({{ $archivedTasksTotal }} total)</span>
                                        @endif
                                    </p>
                                @endif
                                <div x-ref="list"><x-task-list :tasks="$archivedTasks" :show-as-archived="true" /></div>
                                <div class="mt-4 text-center" x-show="hasMore">
                                    <button @click="loadMore()" :disabled="loading"
                                            class="px-5 py-2 bg-gray-700 hover:bg-gray-600 text-gray-200 text-sm rounded-md disabled:opacity-50 disabled:cursor-wait">
                                        <span x-show="!loading">Load more</span>
                                        <span x-show="loading">Loading…</span>
                                    </button>
                                </div>
                            </div>
                        @endif

                        @if($tasksTotal === 0 && $completedTasksTotal === 0 && $archivedTasksTotal === 0)
                            <p class="text-gray-500 text-center py-8 italic">No tasks found matching your criteria.</p>
                        @endif
                    </div>
                    <div x-show="noResults" x-cloak class="bg-[#202020] p-8 rounded-lg text-center text-gray-400 border border-gray-700">
                        No tasks match your filter.
                    </div>
                </div>
            @endif
        </div>
    </div>

    @push('scripts')
    <script>
        function searchSectionLoader(initialHasMore, ajaxUrl) {
            return {
                hasMore: initialHasMore,
                nextPage: 2,
                loading: false,
                async loadMore() {
                    if (this.loading || !this.hasMore) return;
                    this.loading = true;
                    try {
                        const res  = await fetch(ajaxUrl + '&page=' + this.nextPage, {
                            headers: { 'X-Requested-With': 'XMLHttpRequest' }
                        });
                        const data = await res.json();
                        this.$refs.list.insertAdjacentHTML('beforeend', data.html);
                        this.hasMore  = data.hasMore;
                        this.nextPage = data.nextPage;
                    } catch (e) {
                        console.error('Failed to load more search results:', e);
                    } finally {
                        this.loading = false;
                    }
                },
            };
        }

        function searchFilter(projects, tags, initialExpanded) {
            return {
                projects: projects,
                tags: tags,
                expanded: initialExpanded,
                searchInput: @js(request('q', '')),
                queryText: @js(request('q', '')),
                selectedProjectId: @js(request('project_id', 'none')),
                selectedTagIds: @js(request('tag_ids', [])),

                // Autocomplete state
                showAutocomplete: false,
                autocompleteType: null, // 'project' or 'tag'
                autocompleteIndex: 0,
                autocompleteQuery: '',

                init() {
                    // Initialize search input from URL parameters
                    this.rebuildSearchInput();
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

                handleInput(event) {
                    const input = this.searchInput;
                    const cursorPos = event.target.selectionStart;

                    // Find the word at cursor position
                    const beforeCursor = input.substring(0, cursorPos);
                    const afterCursor = input.substring(cursorPos);

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

                    const maxIndex = this.autocompleteType === 'project'
                        ? this.filteredProjects.length  // +1 for inbox option
                        : this.filteredTags.length - 1;

                    if (event.key === 'ArrowDown') {
                        event.preventDefault();
                        this.autocompleteIndex = Math.min(this.autocompleteIndex + 1, maxIndex);
                    } else if (event.key === 'ArrowUp') {
                        event.preventDefault();
                        this.autocompleteIndex = Math.max(this.autocompleteIndex - 1, 0);
                    } else if (event.key === 'Enter' && this.showAutocomplete) {
                        event.preventDefault();

                        if (this.autocompleteType === 'project') {
                            const selected = this.autocompleteIndex === 0
                                ? 'inbox'
                                : this.filteredProjects[this.autocompleteIndex - 1]?.name;
                            if (selected) this.selectAutocomplete(selected);
                        } else if (this.autocompleteType === 'tag') {
                            const selected = this.filteredTags[this.autocompleteIndex]?.tag_name;
                            if (selected) this.selectAutocomplete(selected);
                        }
                    } else if (event.key === 'Escape') {
                        event.preventDefault();
                        this.showAutocomplete = false;
                    }
                },

                selectAutocomplete(name) {
                    const input = this.searchInput;
                    const inputEl = this.$refs.searchInput;
                    const cursorPos = inputEl.selectionStart;
                    const beforeCursor = input.substring(0, cursorPos);
                    const afterCursor = input.substring(cursorPos);

                    // Replace the incomplete word with the selected name
                    let newBefore;
                    if (this.autocompleteType === 'project') {
                        const slug = name.toLowerCase().replace(/[^a-z0-9]/g, '');
                        newBefore = beforeCursor.replace(/#\w*$/, '#' + slug + ' ');
                    } else {
                        const slug = name.toLowerCase().replace(/[^a-z0-9]/g, '');
                        newBefore = beforeCursor.replace(/@\w*$/, '@' + slug + ' ');
                    }

                    this.searchInput = newBefore + afterCursor;
                    this.showAutocomplete = false;

                    // Parse and update filters
                    this.parseSearchInput();

                    // Refocus input
                    this.$nextTick(() => {
                        inputEl.focus();
                        inputEl.setSelectionRange(newBefore.length, newBefore.length);
                    });
                },

                hideAutocomplete() {
                    // Delay to allow click events to fire
                    setTimeout(() => {
                        this.showAutocomplete = false;
                    }, 200);
                },

                parseSearchInput() {
                    let input = this.searchInput;
                    let projectMatches = input.match(/#(\w+)/g) || [];
                    let tagMatches = input.match(/@(\w+)/g) || [];

                    // Extract plain text (remove # and @ syntax)
                    let plainText = input
                        .replace(/#\w+/g, '')
                        .replace(/@\w+/g, '')
                        .trim()
                        .replace(/\s+/g, ' ');

                    this.queryText = plainText;

                    // Find project by name
                    if (projectMatches.length > 0) {
                        let projectName = projectMatches[0].substring(1).toLowerCase();
                        if (projectName === 'inbox') {
                            this.selectedProjectId = 'inbox';
                        } else {
                            let project = this.projects.find(p =>
                                p.name.toLowerCase().replace(/[^a-z0-9]/g, '') === projectName.replace(/[^a-z0-9]/g, '')
                            );
                            this.selectedProjectId = project ? project.id : 'none';
                        }
                    } else {
                        this.selectedProjectId = 'none';
                    }

                    // Find tags by name
                    this.selectedTagIds = [];
                    tagMatches.forEach(match => {
                        let tagName = match.substring(1).toLowerCase();
                        let tag = this.tags.find(t =>
                            t.tag_name.toLowerCase().replace(/[^a-z0-9]/g, '') === tagName.replace(/[^a-z0-9]/g, '')
                        );
                        if (tag && !this.selectedTagIds.includes(tag.id)) {
                            this.selectedTagIds.push(tag.id);
                        }
                    });
                },

                updateSearchFromFilters() {
                    this.rebuildSearchInput();
                },

                toggleTag(tagId) {
                    if (this.selectedTagIds.includes(tagId)) {
                        this.selectedTagIds = this.selectedTagIds.filter(id => id !== tagId);
                    } else {
                        this.selectedTagIds.push(tagId);
                    }
                    this.rebuildSearchInput();
                },

                rebuildSearchInput() {
                    let parts = [];

                    // Add plain query text
                    if (this.queryText) {
                        parts.push(this.queryText);
                    }

                    // Add project syntax
                    if (this.selectedProjectId && this.selectedProjectId !== 'none') {
                        if (this.selectedProjectId === 'inbox') {
                            parts.push('#inbox');
                        } else {
                            let project = this.projects.find(p => p.id == this.selectedProjectId);
                            if (project) {
                                let projectSlug = project.name.toLowerCase().replace(/[^a-z0-9]/g, '');
                                parts.push('#' + projectSlug);
                            }
                        }
                    }

                    // Add tag syntax
                    this.selectedTagIds.forEach(tagId => {
                        let tag = this.tags.find(t => t.id == tagId);
                        if (tag) {
                            let tagSlug = tag.tag_name.toLowerCase().replace(/[^a-z0-9]/g, '');
                            parts.push('@' + tagSlug);
                        }
                    });

                    this.searchInput = parts.join(' ');
                },

                prepareSubmit(e) {
                    // Make sure hidden fields are up to date before submitting
                    this.parseSearchInput();
                }
            };
        }
    </script>
    @endpush
</x-app-layout>
