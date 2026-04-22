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
                <div x-data="{
                    editing: false,
                    input: '',
                    error: false,
                    currentDate: '{{ $carbonDate->format('Y-m-d') }}',
                    activate() {
                        this.input = this.currentDate;
                        this.error = false;
                        this.editing = true;
                        this.$nextTick(() => { this.$refs.dateInput.focus(); this.$refs.dateInput.select(); });
                    },
                    cancel() {
                        this.editing = false;
                        this.error = false;
                    },
                    async navigate() {
                        const val = this.input.trim();
                        if (!val) { this.cancel(); return; }
                        const resp = await fetch('{{ route('tasks.parseDate') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({ input: val })
                        });
                        const data = await resp.json();
                        if (data.success) {
                            if (data.date === this.currentDate) { this.cancel(); return; }
                            window.location.href = '{{ route('day') }}?date=' + data.date;
                        } else {
                            this.error = true;
                        }
                    },
                    pickDate(value) {
                        if (!value || value === this.currentDate) return;
                        window.location.href = '{{ route('day') }}?date=' + value;
                    }
                }" @click.outside="cancel()">
                    <h2 x-show="!editing"
                        @click="activate()"
                        class="font-semibold text-xl text-gray-100 leading-tight cursor-pointer hover:text-gray-300 transition-colors select-none"
                        title="Click to jump to a different date">
                        @if($carbonDate->isToday()) {{ __('Today') }} - @endif{{ $carbonDate->format('l, F j, Y') }}
                        <x-task-count-badge :count="$tasks->count()" :breakdown="$breakdown" />
                    </h2>
                    <div x-show="editing" x-cloak class="flex items-center gap-1.5">
                        <input
                            x-ref="dateInput"
                            x-model="input"
                            @keydown.enter.prevent="navigate()"
                            @keydown.escape.prevent="cancel()"
                            @input="error = false"
                            @click.stop
                            :class="error ? 'border-red-500 focus:ring-red-500' : 'border-gray-600 focus:ring-blue-500'"
                            class="bg-gray-700 border rounded px-2 py-0.5 text-gray-100 text-base font-semibold focus:outline-none focus:ring-2 w-44"
                        />
                        <input type="date" x-ref="calendarInput"
                            style="position:absolute;opacity:0;width:1px;height:1px;overflow:hidden;pointer-events:none;"
                            :value="currentDate" @change="pickDate($event.target.value)" />
                        <button type="button" @click.stop="$refs.calendarInput.showPicker ? $refs.calendarInput.showPicker() : $refs.calendarInput.click()"
                            title="Pick from calendar"
                            class="text-gray-400 hover:text-gray-100 cursor-pointer">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </button>
                        <span x-show="error" class="text-red-400 text-xs">Can't parse date</span>
                    </div>
                </div>
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
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            {{-- Controls bar: view toggle + 24h toggle --}}
            <div class="flex justify-end items-center mb-2" x-data>

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
            <div x-cloak x-show="$store.dayView.current === 'list'" x-data="taskFilter(@js($projects), @js($tags), @js($users), @js($locations))">
                {{-- Fold chevron + sort row --}}
                <div class="flex items-center justify-between mb-2">
                    <button type="button"
                            @click="showIncomplete = !showIncomplete"
                            title="Toggle task list"
                            class="text-gray-500 hover:text-gray-300 transition-colors flex-shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg"
                             class="h-4 w-4 transition-transform duration-150"
                             :class="showIncomplete ? 'rotate-90' : ''"
                             fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                    <div class="flex items-center gap-2">
                        <select id="sort-select" onchange="(function(v){const p=new URLSearchParams(window.location.search);p.set('sort',v);localStorage.setItem('task_sort_'+window.location.pathname,v);window.location.href=window.location.pathname+'?'+p.toString()})(this.value)"
                                class="text-sm bg-gray-700 border border-gray-600 rounded px-2 py-1 text-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="date" {{ $sort === 'date' ? 'selected' : '' }}>Date & Time</option>
                            <option value="created" {{ $sort === 'created' ? 'selected' : '' }}>Date Added</option>
                            <option value="name" {{ $sort === 'name' ? 'selected' : '' }}>Name</option>
                            <option value="location" {{ $sort === 'location' ? 'selected' : '' }}>Location</option>
                            <option value="custom" {{ $sort === 'custom' ? 'selected' : '' }}>Custom Sort</option>
                        </select>
                        @if($sort !== 'custom')
                        <button onclick="toggleSortReversed()"
                                title="{{ request()->boolean('reversed') ? 'Reversed — click to restore' : 'Reverse sort order' }}"
                                class="p-1 rounded transition-colors {{ request()->boolean('reversed') ? 'text-blue-400 hover:text-blue-300' : 'text-gray-500 hover:text-gray-300' }}">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7 16V4M3 8l4-4 4 4M17 8v12M13 16l4 4 4-4" />
                            </svg>
                        </button>
                        @endif
                    </div>
                </div>
                <div x-show="showIncomplete">
                    <x-task-input-bar :date="$carbonDate->format('Y-m-d')" />
                    <div x-ref="taskContainer">
                        <x-task-list :tasks="$tasks" :hide-date="true" :view-date="$carbonDate->format('Y-m-d')" :sortable="$sort === 'custom'" />
                    </div>
                    <div x-show="noResults" x-cloak class="bg-[#202020] p-8 rounded-lg text-center text-gray-400 border border-gray-700">
                        No tasks match your filter.
                    </div>
                </div>
                <x-completed-tasks-section
                    :tasks="$completedTasks"
                    :hide-date="true"
                    :total-count="$completedTasksTotal"
                    :has-more="$completedTasksHasMore"
                    :ajax-url="$completedTasksHasMore ? route('day.completedTasks') . '?date=' . $date : null" />
                <x-completed-tasks-section
                    :tasks="$archivedTasks"
                    label="Archived tasks"
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
