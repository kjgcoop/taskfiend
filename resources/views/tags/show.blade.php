<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-100 leading-tight" style="color: {{ $tag->color }}">
                {{ $tag->tag_name }}
            </h2>
            <div class="flex gap-2">
                <a href="{{ route('tags.edit', $tag) }}" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                    Edit
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <!-- Tag Details -->
            <div class="bg-[#0a1a0a] border border-gray-700 overflow-hidden shadow-sm sm:rounded-lg p-6">
                <div class="flex items-center gap-4">
                    <div class="w-16 h-16 rounded-lg" style="background-color: {{ $tag->color }}"></div>
                    <div>
                        <h3 class="font-semibold text-lg text-gray-100">{{ $tag->tag_name }}</h3>
                        <p class="text-sm text-gray-500">Color: {{ $tag->color }}</p>
                    </div>
                </div>
            </div>

            <!-- Tagged Tasks -->
            <div class="bg-[#0a1a0a] border border-gray-700 overflow-hidden shadow-sm sm:rounded-lg p-6" x-data="taskFilter()">
                <h3 class="text-lg font-semibold text-gray-100 mb-4">Tagged Tasks
                    <span class="text-sm text-gray-500 font-normal" x-text="$store.taskCount.ready ? ($store.taskCount.filtered ? 'showing ' + $store.taskCount.visible + ' of ' + $store.taskCount.total : $store.taskCount.total) : ''"></span>
                </h3>
                <div class="mb-4">
                    <input type="text"
                           x-model="query"
                           x-on:input="filterTasks()"
                           x-on:keydown.escape="clearFilter()"
                           placeholder="Filter tasks... (# project)"
                           class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-md text-gray-100 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <div x-ref="taskContainer">
                    <x-task-list :tasks="$tasks" />
                </div>
                <div x-show="noResults" x-cloak class="bg-[#0a1a0a] p-8 rounded-lg text-center text-gray-400 border border-gray-700">
                    No tasks match your filter.
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
