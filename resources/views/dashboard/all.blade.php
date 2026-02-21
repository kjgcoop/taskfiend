<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-100 leading-tight">
                {{ __('All Tasks') }}
                <span class="text-sm text-gray-500 font-normal" x-data x-text="$store.taskCount.ready ? ($store.taskCount.filtered ? 'showing ' + $store.taskCount.visible + ' of ' + $store.taskCount.total : $store.taskCount.total) : '{{ $tasks->count() }}'">{{ $tasks->count() }}</span>
            </h2>
            <a href="{{ route('tasks.create') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700">
                Add Task
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8" x-data="taskFilter(@js($projects), @js($tags))">
            <div class="mb-4 text-sm text-gray-600">
                All tasks you have access to, excluding archived and done.
            </div>
            <x-task-input-bar filter-placeholder="Filter tasks... (@ tag, # project)" />
            <div x-ref="taskContainer">
                <x-task-list :tasks="$tasks" />
            </div>
            <div x-show="noResults" x-cloak class="bg-[#202020] p-8 rounded-lg text-center text-gray-400 border border-gray-700">
                No tasks match your filter.
            </div>
        </div>
    </div>
</x-app-layout>
