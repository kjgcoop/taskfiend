<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-100 leading-tight">
                {{ __('Inbox') }}
                <span class="text-sm text-gray-500 font-normal" x-data x-text="$store.taskCount.ready ? ($store.taskCount.filtered ? 'showing ' + $store.taskCount.visible + ' of ' + $store.taskCount.total : $store.taskCount.total) : '{{ $tasks->count() }}'">{{ $tasks->count() }}</span>
            </h2>
            <a href="{{ route('tasks.create') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700">
                Add Task
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8" x-data="taskFilter()">
            <div class="mb-4 text-sm text-gray-600">
                Tasks with no project assigned
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
            <div x-show="noResults" x-cloak class="bg-[#002200] p-8 rounded-lg text-center text-gray-400 border border-gray-700">
                No tasks match your filter.
            </div>
        </div>
    </div>
</x-app-layout>
