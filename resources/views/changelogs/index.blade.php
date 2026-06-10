<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-100 leading-tight task-title">
            @if(isset($task))
                Change Log - {!! render_title($task->name) !!}
            @elseif(isset($project))
                Change Log - {{ $project->name }}
            @elseif(isset($tag))
                Change Log - {{ $tag->tag_name }}
            @else
                My Activity
            @endif
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">

            {{-- ── Filters (user activity view only) ── --}}
            @if(!isset($task) && !isset($project) && !isset($tag))
            <div class="bg-[#202020] border border-gray-700 rounded-lg p-4">
                <form id="activity-filter-form" method="GET" action="{{ route('changelogs.user') }}">
                    <div class="flex flex-wrap gap-3 items-end">

                        {{-- Search --}}
                        <div class="flex-1 min-w-48">
                            <label class="block text-xs text-gray-400 mb-1">Search</label>
                            <input type="text" name="search"
                                   value="{{ request('search') }}"
                                   placeholder="Description or task name…"
                                   class="w-full bg-gray-700 border border-gray-600 text-gray-100 placeholder-gray-500 rounded-md px-3 py-1.5 text-sm focus:outline-none focus:border-blue-500">
                        </div>

                        {{-- Project multi-select --}}
                        <div class="min-w-44" x-data="multiSelect({{ json_encode(request('projects', [])) }})">
                            <label class="block text-xs text-gray-400 mb-1">Project</label>
                            <div class="relative">
                                <button type="button" @click="open = !open"
                                        class="w-full bg-gray-700 border border-gray-600 text-sm text-left rounded-md px-3 py-1.5 flex items-center justify-between"
                                        :class="selected.length ? 'text-gray-100' : 'text-gray-500'">
                                    <span x-text="selected.length ? selected.length + ' selected' : 'Any project'"></span>
                                    <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </button>
                                <div x-show="open" @click.outside="open = false"
                                     class="absolute z-20 mt-1 w-full bg-gray-800 border border-gray-600 rounded-md shadow-lg max-h-52 overflow-y-auto">
                                    @forelse($availableProjects as $proj)
                                    <label class="flex items-center gap-2 px-3 py-2 hover:bg-gray-700 cursor-pointer text-sm text-gray-200">
                                        <input type="checkbox" name="projects[]" value="{{ $proj->id }}"
                                               x-model="selected" class="rounded border-gray-500 bg-gray-600 text-blue-500">
                                        {{ $proj->name }}
                                    </label>
                                    @empty
                                        <p class="px-3 py-2 text-sm text-gray-500">No projects</p>
                                    @endforelse
                                </div>
                            </div>
                        </div>

                        {{-- Tag multi-select --}}
                        <div class="min-w-44" x-data="multiSelect({{ json_encode(request('tags', [])) }})">
                            <label class="block text-xs text-gray-400 mb-1">Tag</label>
                            <div class="relative">
                                <button type="button" @click="open = !open"
                                        class="w-full bg-gray-700 border border-gray-600 text-sm text-left rounded-md px-3 py-1.5 flex items-center justify-between"
                                        :class="selected.length ? 'text-gray-100' : 'text-gray-500'">
                                    <span x-text="selected.length ? selected.length + ' selected' : 'Any tag'"></span>
                                    <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </button>
                                <div x-show="open" @click.outside="open = false"
                                     class="absolute z-20 mt-1 w-full bg-gray-800 border border-gray-600 rounded-md shadow-lg max-h-52 overflow-y-auto">
                                    @forelse($availableTags as $t)
                                    <label class="flex items-center gap-2 px-3 py-2 hover:bg-gray-700 cursor-pointer text-sm text-gray-200">
                                        <input type="checkbox" name="tags[]" value="{{ $t->id }}"
                                               x-model="selected" class="rounded border-gray-500 bg-gray-600 text-blue-500">
                                        @if($t->color)
                                            <span class="inline-block w-2 h-2 rounded-full flex-shrink-0" style="background-color: {{ $t->color }}"></span>
                                        @endif
                                        {{ $t->tag_name }}
                                    </label>
                                    @empty
                                        <p class="px-3 py-2 text-sm text-gray-500">No tags</p>
                                    @endforelse
                                </div>
                            </div>
                        </div>

                        {{-- Entity type --}}
                        <div class="min-w-36">
                            <label class="block text-xs text-gray-400 mb-1">Type</label>
                            <select name="entity_type"
                                    class="w-full bg-gray-700 border border-gray-600 text-gray-100 rounded-md px-3 py-1.5 text-sm focus:outline-none focus:border-blue-500">
                                <option value="">All types</option>
                                <option value="tasks"    @selected(request('entity_type') === 'tasks')>Tasks</option>
                                <option value="projects" @selected(request('entity_type') === 'projects')>Projects</option>
                                <option value="tags"     @selected(request('entity_type') === 'tags')>Tags</option>
                            </select>
                        </div>

                        {{-- Date from --}}
                        <div>
                            <label class="block text-xs text-gray-400 mb-1">From</label>
                            <input type="date" name="date_from"
                                   value="{{ request('date_from') }}"
                                   class="bg-gray-700 border border-gray-600 text-gray-100 rounded-md px-3 py-1.5 text-sm focus:outline-none focus:border-blue-500">
                        </div>

                        {{-- Date to --}}
                        <div>
                            <label class="block text-xs text-gray-400 mb-1">To</label>
                            <input type="date" name="date_to"
                                   value="{{ request('date_to') }}"
                                   class="bg-gray-700 border border-gray-600 text-gray-100 rounded-md px-3 py-1.5 text-sm focus:outline-none focus:border-blue-500">
                        </div>

                        {{-- Action buttons --}}
                        <div class="flex gap-2 items-end pb-0.5">
                            <button type="submit"
                                    class="px-4 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded-md">
                                Filter
                            </button>
                            @if(request()->hasAny(['search', 'projects', 'tags', 'entity_type', 'date_from', 'date_to']))
                            <a href="{{ route('changelogs.user') }}"
                               class="px-4 py-1.5 bg-gray-600 hover:bg-gray-500 text-white text-sm rounded-md">
                                Clear
                            </a>
                            @endif
                        </div>

                    </div>
                </form>
            </div>
            @endif

            {{-- ── Log entries ── --}}
            <div class="bg-[#202020] border border-gray-700 overflow-hidden shadow-sm sm:rounded-lg p-6">

                @if(isset($task))
                    <div class="mb-4">
                        <a href="{{ route('tasks.show', $task) }}" class="text-sm text-blue-400 hover:underline">
                            &larr; Back to Task
                        </a>
                    </div>
                    <div x-data="changelogLoader('{{ route('changelogs.task', $task) }}', {{ $hasMore ? 'true' : 'false' }}, {{ $page + 1 }})">
                        <div x-ref="entries" class="space-y-2">
                            @include('changelogs.partials.context-entries')
                        </div>
                        <div class="mt-6 text-center" x-show="hasMore">
                            <button @click="loadMore()" :disabled="loading"
                                    class="px-5 py-2 bg-gray-700 hover:bg-gray-600 text-gray-200 text-sm rounded-md disabled:opacity-50 disabled:cursor-wait">
                                <span x-show="!loading">Load more</span>
                                <span x-show="loading">Loading…</span>
                            </button>
                        </div>
                        <p x-show="!hasMore && nextPage > 2" class="mt-4 text-center text-xs text-gray-600">All events loaded.</p>
                    </div>

                @elseif(isset($project))
                    <div class="mb-4">
                        <a href="{{ route('projects.show', $project) }}" class="text-sm text-blue-400 hover:underline">
                            &larr; Back to Project
                        </a>
                    </div>
                    <div x-data="changelogLoader('{{ route('changelogs.project', $project) }}', {{ $hasMore ? 'true' : 'false' }}, {{ $page + 1 }})">
                        <div x-ref="entries" class="space-y-2">
                            @include('changelogs.partials.context-entries')
                        </div>
                        <div class="mt-6 text-center" x-show="hasMore">
                            <button @click="loadMore()" :disabled="loading"
                                    class="px-5 py-2 bg-gray-700 hover:bg-gray-600 text-gray-200 text-sm rounded-md disabled:opacity-50 disabled:cursor-wait">
                                <span x-show="!loading">Load more</span>
                                <span x-show="loading">Loading…</span>
                            </button>
                        </div>
                        <p x-show="!hasMore && nextPage > 2" class="mt-4 text-center text-xs text-gray-600">All events loaded.</p>
                    </div>

                @elseif(isset($tag))
                    <div class="mb-4">
                        <a href="{{ route('tags.show', $tag) }}" class="text-sm text-blue-400 hover:underline">
                            &larr; Back to Tag
                        </a>
                    </div>
                    <div x-data="changelogLoader('{{ route('changelogs.tag', $tag) }}', {{ $hasMore ? 'true' : 'false' }}, {{ $page + 1 }})">
                        <div x-ref="entries" class="space-y-2">
                            @include('changelogs.partials.context-entries')
                        </div>
                        <div class="mt-6 text-center" x-show="hasMore">
                            <button @click="loadMore()" :disabled="loading"
                                    class="px-5 py-2 bg-gray-700 hover:bg-gray-600 text-gray-200 text-sm rounded-md disabled:opacity-50 disabled:cursor-wait">
                                <span x-show="!loading">Load more</span>
                                <span x-show="loading">Loading…</span>
                            </button>
                        </div>
                        <p x-show="!hasMore && nextPage > 2" class="mt-4 text-center text-xs text-gray-600">All events loaded.</p>
                    </div>

                @else
                    {{-- User activity view with lazy loading --}}
                    <div x-data="activityLoader({{ $hasMore ? 'true' : 'false' }}, {{ $page + 1 }})">

                        <div id="activity-entries" class="space-y-2">
                            @include('changelogs.partials.entries')
                        </div>

                        <div class="mt-6 text-center" x-show="hasMore">
                            <button @click="loadMore()"
                                    :disabled="loading"
                                    class="px-5 py-2 bg-gray-700 hover:bg-gray-600 text-gray-200 text-sm rounded-md disabled:opacity-50 disabled:cursor-wait">
                                <span x-show="!loading">Load more</span>
                                <span x-show="loading">Loading…</span>
                            </button>
                        </div>

                        <p x-show="!hasMore && nextPage > 2"
                           class="mt-4 text-center text-xs text-gray-600">
                            All events loaded.
                        </p>

                    </div>
                @endif

            </div>
        </div>
    </div>

    <script nonce="{{ csp_nonce() }}">
    function changelogLoader(url, initialHasMore, initialNextPage) {
        return {
            hasMore:  initialHasMore,
            nextPage: initialNextPage,
            loading:  false,

            async loadMore() {
                if (this.loading || !this.hasMore) return;
                this.loading = true;
                try {
                    const res  = await fetch(url + '?page=' + this.nextPage, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    const data = await res.json();
                    this.$refs.entries.insertAdjacentHTML('beforeend', data.html);
                    this.hasMore  = data.hasMore;
                    this.nextPage = data.nextPage;
                } catch (e) {
                    console.error('Failed to load more changes:', e);
                } finally {
                    this.loading = false;
                }
            },
        };
    }
    </script>

    @if(!isset($task) && !isset($project) && !isset($tag))
    <script nonce="{{ csp_nonce() }}">
    function multiSelect(initialValues) {
        return {
            open: false,
            selected: (initialValues || []).map(String),
        };
    }

    function activityLoader(initialHasMore, initialNextPage) {
        return {
            hasMore:  initialHasMore,
            nextPage: initialNextPage,
            loading:  false,

            async loadMore() {
                if (this.loading || !this.hasMore) return;
                this.loading = true;

                const form   = document.getElementById('activity-filter-form');
                const params = new URLSearchParams(new FormData(form));
                params.set('page', this.nextPage);

                try {
                    const res  = await fetch('{{ route('changelogs.user') }}?' + params.toString(), {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    const data = await res.json();

                    document.getElementById('activity-entries')
                        .insertAdjacentHTML('beforeend', data.html);
                    this.hasMore  = data.hasMore;
                    this.nextPage = data.nextPage;
                } catch (e) {
                    console.error('Failed to load more activity:', e);
                } finally {
                    this.loading = false;
                }
            },
        };
    }
    </script>
    @endif
</x-app-layout>
