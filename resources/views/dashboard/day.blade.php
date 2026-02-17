<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div class="flex items-center gap-3">
                @if($carbonDate->gt(today()))
                    @if($carbonDate->copy()->subDay()->isToday())
                        <a href="{{ route('today') }}" class="text-gray-400 hover:text-gray-100" title="Today">
                    @else
                        <a href="{{ route('day') }}?date={{ $carbonDate->copy()->subDay()->format('Y-m-d') }}" class="text-gray-400 hover:text-gray-100" title="{{ $carbonDate->copy()->subDay()->format('l, F j, Y') }}">
                    @endif
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                        </svg>
                    </a>
                @endif
                <h2 class="font-semibold text-xl text-gray-100 leading-tight">
                    {{ $carbonDate->format('l, F j, Y') }}
                    <span class="text-sm italic text-gray-500 font-normal">{{ $tasks->count() }}</span>
                </h2>
                <a href="{{ route('day') }}?date={{ $carbonDate->copy()->addDay()->format('Y-m-d') }}" class="text-gray-400 hover:text-gray-100" title="{{ $carbonDate->copy()->addDay()->format('l, F j, Y') }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                </a>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('tasks.create') }}?date={{ $carbonDate->format('Y-m-d') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700">
                    Add Task
                </a>
                <a href="{{ route('calendar') }}" class="inline-flex items-center px-4 py-2 bg-gray-700 border border-gray-600 rounded-md font-semibold text-xs text-gray-300 uppercase tracking-widest hover:bg-gray-600">
                    Back to Calendar
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8" x-data="taskFilter()">
            <div class="mb-4">
                <input type="text"
                       x-model="query"
                       x-on:input="filterTasks()"
                       placeholder="Filter tasks... (# project, @ tag)"
                       class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-md text-gray-100 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>
            <div x-ref="taskContainer">
                <x-task-list :tasks="$tasks" />
            </div>
            <div x-show="noResults" x-cloak class="bg-gray-800 p-8 rounded-lg text-center text-gray-400 border border-gray-700">
                No tasks match your filter.
            </div>
        </div>
    </div>
</x-app-layout>
