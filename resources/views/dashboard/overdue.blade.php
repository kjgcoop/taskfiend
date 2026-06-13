<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-100 leading-tight">
                {{ __('Overdue') }}
                <x-task-count-badge :count="$totalCount" :breakdown="$breakdown" />
            </h2>
            <a href="{{ route('tasks.create') }}" class="inline-flex items-center gap-1 px-3 py-2 bg-blue-600 border border-transparent rounded-md text-white hover:bg-blue-700" title="Add Task">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                </svg>
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8"
             x-data="taskFilter"
             data-projects="@json($projects)"
             data-tags="@json($tags)"
             data-users="@json($users)"
             data-locations="@json($locations)">
            <div class="flex justify-end items-center gap-2 mb-2">
                <label class="text-gray-400 text-sm mr-2 self-center">Sort: </label>
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
            <x-task-input-bar />
            <div x-ref="taskContainer">
                <x-task-list :tasks="$tasks" :sortable="$sort === 'custom'" />
            </div>
            <div x-show="noResults" x-cloak class="bg-[#202020] p-8 rounded-lg text-center text-gray-400 border border-gray-700">
                No tasks match your filter.
            </div>

            @if($totalCount > $perPage)
            <div class="flex items-center justify-between mt-4 text-sm text-gray-400">
                <span>
                    Showing {{ ($page - 1) * $perPage + 1 }}–{{ min($page * $perPage, $totalCount) }} of {{ $totalCount }}
                </span>
                <div class="flex gap-2">
                    @if($page > 1)
                        <a href="{{ request()->fullUrlWithQuery(['page' => $page - 1]) }}"
                           class="px-3 py-1 bg-gray-700 border border-gray-600 rounded hover:bg-gray-600 text-gray-300">
                            &lsaquo; Prev
                        </a>
                    @endif
                    @if($hasMore)
                        <a href="{{ request()->fullUrlWithQuery(['page' => $page + 1]) }}"
                           class="px-3 py-1 bg-gray-700 border border-gray-600 rounded hover:bg-gray-600 text-gray-300">
                            Next &rsaquo;
                        </a>
                    @endif
                </div>
            </div>
            @endif
        </div>
    </div>
</x-app-layout>
