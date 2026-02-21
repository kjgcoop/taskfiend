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
            <div class="bg-[#202020] border border-gray-700 overflow-hidden shadow-sm sm:rounded-lg p-6">
                <div class="flex items-center gap-4">
                    <div class="w-16 h-16 rounded-lg" style="background-color: {{ $tag->color }}"></div>
                    <div>
                        <h3 class="font-semibold text-lg text-gray-100">{{ $tag->tag_name }}</h3>
                        <p class="text-sm text-gray-500">Color: {{ $tag->color }}</p>
                    </div>
                </div>
            </div>

            <!-- Tagged Tasks -->
            <div class="bg-[#202020] border border-gray-700 overflow-hidden shadow-sm sm:rounded-lg p-6" x-data="taskFilter(@js($projects), @js($allTags))">
                <h3 class="text-lg font-semibold text-gray-100 mb-4">Tagged Tasks
                    <span class="text-sm text-gray-500 font-normal" x-text="$store.taskCount.ready ? ($store.taskCount.filtered ? 'showing ' + $store.taskCount.visible + ' of ' + $store.taskCount.total : $store.taskCount.total) : ''"></span>
                </h3>
                <x-task-input-bar :tag-id="$tag->id" filter-placeholder="Filter tasks... (# project)" />
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
</x-app-layout>
