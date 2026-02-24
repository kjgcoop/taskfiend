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
                {{-- View toggle (client-side via Alpine store) --}}
                <div class="flex rounded-md border border-gray-600 overflow-hidden" x-data>
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

                {{-- Full-day toggle (only visible in agenda view) --}}
                <div x-data x-show="$store.dayView.current === 'agenda'" x-cloak>
                    <button @click="$store.agendaFull.toggle()"
                            :class="$store.agendaFull.on ? 'bg-gray-600 text-gray-100' : 'bg-gray-800 text-gray-400 hover:bg-gray-700 hover:text-gray-200'"
                            class="px-3 py-2 rounded-md border border-gray-600 text-xs font-medium transition-colors"
                            title="Toggle full 24-hour view">
                        <span x-text="$store.agendaFull.on ? '24h' : 'Auto'"></span>
                    </button>
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
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            {{-- Agenda view --}}
            <div x-data x-cloak x-show="$store.dayView.current === 'agenda'">
                @include('dashboard.partials.agenda')
            </div>

            {{-- List view --}}
            <div x-cloak x-show="$store.dayView.current === 'list'" x-data="taskFilter(@js($projects), @js($tags))">
                <x-task-input-bar :date="$carbonDate->format('Y-m-d')" />
                <div x-ref="taskContainer">
                    <x-task-list :tasks="$tasks" :hide-date="true" :view-date="$carbonDate->format('Y-m-d')" />
                </div>
                <div x-show="noResults" x-cloak class="bg-[#202020] p-8 rounded-lg text-center text-gray-400 border border-gray-700">
                    No tasks match your filter.
                </div>
                <x-completed-tasks-section :tasks="$completedTasks" :hide-date="true" />
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
