<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div class="flex items-center gap-3">
                @if($carbonDate->gt(today()))
                    <a href="{{ route('day') }}?date={{ $carbonDate->copy()->subDay()->format('Y-m-d') }}" class="text-gray-400 hover:text-gray-100" title="{{ $carbonDate->copy()->subDay()->format('l, F j, Y') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                        </svg>
                    </a>
                @endif
                <h2 class="font-semibold text-xl text-gray-100 leading-tight">
                    @if($carbonDate->isToday()) {{ __('Today') }} - @endif{{ $carbonDate->format('l, F j, Y') }}
                    <span class="text-sm text-gray-500 font-normal" x-data x-text="$store.taskCount.ready ? ($store.taskCount.filtered ? 'showing ' + $store.taskCount.visible + ' of ' + $store.taskCount.total : $store.taskCount.total) : '{{ $tasks->count() }}'">{{ $tasks->count() }}</span>
                </h2>
                <a href="{{ route('day') }}?date={{ $carbonDate->copy()->addDay()->format('Y-m-d') }}" class="text-gray-400 hover:text-gray-100" title="{{ $carbonDate->copy()->addDay()->format('l, F j, Y') }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                </a>
            </div>
            <div class="flex items-center gap-2">
                @if($carbonDate->isToday() && $overdueCount > 0)
                    <a href="{{ route('overdue') }}" class="inline-flex items-center gap-1 px-2.5 py-1.5 bg-red-900/40 border border-red-700/50 rounded-md text-sm text-red-400 hover:text-red-300 hover:bg-red-900/60 transition-colors" title="{{ $overdueCount }} overdue">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        {{ $overdueCount }}
                    </a>
                @endif
                <a href="{{ route('day.export-markdown') }}?date={{ $carbonDate->format('Y-m-d') }}" class="hidden sm:inline-flex items-center px-4 py-2 bg-gray-700 border border-gray-600 rounded-md font-semibold text-xs text-gray-100 uppercase tracking-widest hover:bg-gray-600">
                    Export .md
                </a>
                <a href="{{ route('tasks.create') }}?date={{ $carbonDate->format('Y-m-d') }}" class="inline-flex items-center gap-1 px-3 py-2 bg-blue-600 border border-transparent rounded-md text-white hover:bg-blue-700" title="Add Task">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    <span class="hidden sm:inline font-semibold text-xs uppercase tracking-widest">Add Task</span>
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            {{-- Controls bar: sort (list only) + view toggle + 24h toggle --}}
            <div class="flex justify-between items-center mb-2" x-data>
                {{-- Sort (list view only) --}}
                <div x-show="$store.dayView.current === 'list'" x-cloak>
                    <select id="sort-select" onchange="(function(v){const p=new URLSearchParams(window.location.search);p.set('sort',v);localStorage.setItem('task_sort_'+window.location.pathname,v);window.location.href=window.location.pathname+'?'+p.toString()})(this.value)"
                            class="text-sm bg-gray-700 border border-gray-600 rounded px-2 py-1 text-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="date" {{ $sort === 'date' ? 'selected' : '' }}>Date & Time</option>
                        <option value="created" {{ $sort === 'created' ? 'selected' : '' }}>Date Added</option>
                        <option value="name" {{ $sort === 'name' ? 'selected' : '' }}>Name (A–Z)</option>
                        <option value="custom" {{ $sort === 'custom' ? 'selected' : '' }}>Custom Sort</option>
                    </select>
                </div>
                <div x-show="$store.dayView.current !== 'list'" x-cloak></div>

                <div class="flex items-center gap-2">
                    {{-- Full-day toggle (agenda only) --}}
                    <div x-show="$store.dayView.current === 'agenda'" x-cloak>
                        <button @click="$store.agendaFull.toggle()"
                                :class="$store.agendaFull.on ? 'bg-gray-600 text-gray-100' : 'bg-gray-800 text-gray-400 hover:bg-gray-700 hover:text-gray-200'"
                                class="px-3 py-2 rounded-md border border-gray-600 text-xs font-medium transition-colors"
                                title="Toggle full 24-hour view">
                            <span x-text="$store.agendaFull.on ? '24h' : 'Auto'"></span>
                        </button>
                    </div>

                    {{-- View toggle --}}
                    <div class="flex rounded-md border border-gray-600 overflow-hidden">
                        <button @click="$store.dayView.set('list')"
                                title="List view"
                                :class="$store.dayView.current === 'list' ? 'bg-gray-600 text-gray-100' : 'bg-gray-800 text-gray-400 hover:bg-gray-700 hover:text-gray-200'"
                                class="px-3 py-2 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                            </svg>
                        </button>
                        <button @click="$store.dayView.set('agenda')"
                                title="Agenda view"
                                :class="$store.dayView.current === 'agenda' ? 'bg-gray-600 text-gray-100' : 'bg-gray-800 text-gray-400 hover:bg-gray-700 hover:text-gray-200'"
                                class="px-3 py-2 border-l border-gray-600 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            {{-- Agenda view --}}
            <div x-data x-cloak x-show="$store.dayView.current === 'agenda'">
                @include('dashboard.partials.agenda')
            </div>

            {{-- List view --}}
            <div x-cloak x-show="$store.dayView.current === 'list'" x-data="taskFilter(@js($projects), @js($tags))">
                <x-task-input-bar :date="$carbonDate->format('Y-m-d')" />
                <div x-ref="taskContainer">
                    <x-task-list :tasks="$tasks" :hide-date="true" :view-date="$carbonDate->format('Y-m-d')" :sortable="$sort === 'custom'" />
                </div>
                <div x-show="noResults" x-cloak class="bg-[#202020] p-8 rounded-lg text-center text-gray-400 border border-gray-700">
                    No tasks match your filter.
                </div>
                <x-completed-tasks-section
                    :tasks="$completedTasks"
                    :hide-date="true"
                    :total-count="$completedTasksTotal"
                    :has-more="$completedTasksHasMore"
                    :ajax-url="$completedTasksHasMore ? route('day.completedTasks') . '?date=' . $date : null" />
                <x-completed-tasks-section
                    :tasks="$archivedTasks"
                    label="Show archived tasks"
                    :hide-date="true"
                    :read-only="true"
                    :show-as-archived="true"
                    :total-count="$archivedTasksTotal"
                    :has-more="$archivedTasksHasMore"
                    :ajax-url="$archivedTasksHasMore ? route('day.archivedTasks') . '?date=' . $date : null" />
            </div>

        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.store('dayView', {
                current: localStorage.getItem('day_view') || 'list',
                set(v) {
                    this.current = v;
                    localStorage.setItem('day_view', v);
                },
            });

            Alpine.store('agendaFull', {
                on: localStorage.getItem('agenda_full') === '1',
                toggle() {
                    this.on = !this.on;
                    localStorage.setItem('agenda_full', this.on ? '1' : '0');
                },
            });
        });
    </script>
    @endpush
</x-app-layout>
