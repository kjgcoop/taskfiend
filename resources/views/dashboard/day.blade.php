<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div class="flex items-center gap-3">
                @if($carbonDate->gt(today()))
                    <a href="{{ route('day') }}?date={{ $carbonDate->copy()->subDay()->format('Y-m-d') }}&view={{ $view }}" class="text-gray-400 hover:text-gray-100" title="{{ $carbonDate->copy()->subDay()->format('l, F j, Y') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                        </svg>
                    </a>
                @endif
                <h2 class="font-semibold text-xl text-gray-100 leading-tight">
                    @if($carbonDate->isToday()) {{ __('Today') }} - @endif{{ $carbonDate->format('l, F j, Y') }}
                    <span class="text-sm text-gray-500 font-normal" x-data x-text="$store.taskCount.ready ? ($store.taskCount.filtered ? 'showing ' + $store.taskCount.visible + ' of ' + $store.taskCount.total : $store.taskCount.total) : '{{ $tasks->count() }}'">{{ $tasks->count() }}</span>
                </h2>
                <a href="{{ route('day') }}?date={{ $carbonDate->copy()->addDay()->format('Y-m-d') }}&view={{ $view }}" class="text-gray-400 hover:text-gray-100" title="{{ $carbonDate->copy()->addDay()->format('l, F j, Y') }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                </a>
            </div>
            <div class="flex items-center gap-2">
                {{-- View toggle --}}
                <div class="flex rounded-md border border-gray-600 overflow-hidden">
                    <a href="{{ route('day') }}?date={{ $carbonDate->format('Y-m-d') }}&view=list"
                       title="List view"
                       class="px-3 py-2 transition-colors {{ $view === 'list' ? 'bg-gray-600 text-gray-100' : 'bg-gray-800 text-gray-400 hover:bg-gray-700 hover:text-gray-200' }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                        </svg>
                    </a>
                    <a href="{{ route('day') }}?date={{ $carbonDate->format('Y-m-d') }}&view=agenda"
                       title="Agenda view"
                       class="px-3 py-2 border-l border-gray-600 transition-colors {{ $view === 'agenda' ? 'bg-gray-600 text-gray-100' : 'bg-gray-800 text-gray-400 hover:bg-gray-700 hover:text-gray-200' }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </a>
                </div>

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
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8" x-data="{{ $view === 'list' ? 'taskFilter()' : '{}' }}">

            @if($view === 'agenda')
                @include('dashboard.partials.agenda')
            @else
                <x-task-input-bar :date="$carbonDate->format('Y-m-d')" />
                <div x-ref="taskContainer">
                    <x-task-list :tasks="$tasks" :hide-date="true" />
                </div>
                <div x-show="noResults" x-cloak class="bg-[#202020] p-8 rounded-lg text-center text-gray-400 border border-gray-700">
                    No tasks match your filter.
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
