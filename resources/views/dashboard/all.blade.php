<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-100 leading-tight">
                {{ __('All Tasks') }}
                <x-task-count-badge :count="$tasks->count()" :breakdown="$breakdown" />
            </h2>
            <a href="{{ route('tasks.create') }}" class="inline-flex items-center gap-1 px-3 py-2 bg-blue-600 border border-transparent rounded-md text-white hover:bg-blue-700" title="Add Task">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                </svg>
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8" x-data="taskFilter(@js($projects), @js($tags), @js($users), @js($locations))">
            <div class="mb-4 text-sm text-gray-600">
                All tasks you have access to, excluding archived and done.
            </div>
            <div class="flex justify-end mb-2">
                <label class="text-gray-400 text-sm mr-2 self-center">Sort by:</label>
                <select id="sort-select" onchange="(function(v){const p=new URLSearchParams(window.location.search);p.set('sort',v);localStorage.setItem('task_sort_'+window.location.pathname,v);window.location.href=window.location.pathname+'?'+p.toString()})(this.value)"
                        class="text-sm bg-gray-700 border border-gray-600 rounded px-2 py-1 text-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="date" {{ $sort === 'date' ? 'selected' : '' }}>Date & Time</option>
                    <option value="created" {{ $sort === 'created' ? 'selected' : '' }}>Date Added</option>
                    <option value="name" {{ $sort === 'name' ? 'selected' : '' }}>Name (A–Z)</option>
                    <option value="location" {{ $sort === 'location' ? 'selected' : '' }}>Location (A–Z)</option>
                    <option value="custom" {{ $sort === 'custom' ? 'selected' : '' }}>Custom Sort</option>
                </select>
            </div>
            <x-task-input-bar filter-placeholder="Filter tasks... (@ tag, # project)" />
            <div x-ref="taskContainer">
                <x-task-list :tasks="$tasks" :sortable="$sort === 'custom'" />
            </div>
            <div x-show="noResults" x-cloak class="bg-[#202020] p-8 rounded-lg text-center text-gray-400 border border-gray-700">
                No tasks match your filter.
            </div>
        </div>
    </div>
</x-app-layout>
