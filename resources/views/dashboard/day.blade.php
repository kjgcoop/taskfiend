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
                <div x-data="dayDateEditor"
                     data-current-date="{{ $carbonDate->format('Y-m-d') }}"
                     data-parse-route="{{ route('day') }}"
                     data-parse-date-url="{{ route('tasks.parseDate') }}"
                     @click.outside="cancel()">
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
                    Export MD
                </a>
                <button type="button" x-data="dayPdfExport"
                        title="Printable list of this day's tasks — mirrors the current sort and on-page filter"
                        :disabled="$store.taskCount.ready && $store.taskCount.visible === 0"
                        :class="$store.taskCount.ready && $store.taskCount.visible === 0 ? 'opacity-40 cursor-not-allowed' : 'hover:bg-gray-600'"
                        @click="go()"
                        class="hidden sm:inline-flex items-center px-4 py-2 bg-gray-700 border border-gray-600 rounded-md font-semibold text-xs text-gray-100 uppercase tracking-widest">
                    Export PDF
                </button>

                {{-- Mobile export menu: same two actions, collapsed behind a three-dot
                     menu since the buttons above don't fit next to the date controls
                     in portrait mode on a phone. Menu open/close state and the PDF
                     export live in the same dayPdfExport component instance so
                     selectPdf() can call go() and close the menu in one bare
                     expression (see docs/content/docs/developers/frontend-csp.md —
                     Alpine's CSP-safe parser can't handle "go(); open = false"
                     as a multi-statement @click). --}}
                <div class="relative shrink-0 sm:hidden" x-data="dayPdfExport" @click.outside="close()">
                    <button type="button" @click="toggle()"
                            class="p-2 text-gray-400 hover:text-gray-100 hover:bg-gray-700 rounded transition-colors"
                            title="Export options">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M10 6a2 2 0 110-4 2 2 0 010 4zm0 6a2 2 0 110-4 2 2 0 010 4zm0 6a2 2 0 110-4 2 2 0 010 4z" />
                        </svg>
                    </button>
                    <div x-show="open" x-cloak
                         class="absolute right-0 mt-1 w-40 bg-gray-800 border border-gray-600 rounded shadow-lg z-10">
                        <a href="{{ route('day.export-markdown') }}?date={{ $carbonDate->format('Y-m-d') }}"
                           class="block px-4 py-2 text-gray-200 hover:bg-gray-700">
                            Export MD
                        </a>
                        <button type="button"
                                :disabled="$store.taskCount.ready && $store.taskCount.visible === 0"
                                :class="$store.taskCount.ready && $store.taskCount.visible === 0 ? 'opacity-40 cursor-not-allowed' : 'hover:bg-gray-700'"
                                @click="selectPdf()"
                                class="w-full text-left px-4 py-2 text-gray-200">
                            Export PDF
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            {{-- Stale-page banner: shown when this day page is past its date --}}
            <div x-data="staleBanner" data-date="{{ $carbonDate->format('Y-m-d') }}" data-reload-url="{{ route('day') }}"
                 x-show="stale"
                 x-cloak
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 -translate-y-1"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 class="mb-3 px-4 py-2.5 bg-amber-900/30 border border-amber-700/50 rounded-lg flex items-center justify-between gap-3 flex-wrap">
                <p class="text-sm text-amber-300 min-w-0">
                    You're viewing <span x-text="pageDateLabel"></span>.
                    Tasks added here will be scheduled for <strong x-text="todayLabel"></strong> instead.
                </p>
                <a :href="todayUrl" class="text-sm text-amber-200 hover:text-white underline whitespace-nowrap flex-shrink-0">
                    View today
                </a>
            </div>

            {{-- Project reminders --}}
            @if($projectReminders->count() > 0)
                <div class="mb-3 space-y-2">
                    @foreach($projectReminders as $reminder)
                        <div class="flex items-center justify-between px-4 py-2.5 bg-blue-950/30 border border-blue-700/50 rounded-lg gap-3">
                            <div class="flex items-center gap-2 min-w-0">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                </svg>
                                <a href="{{ route('projects.show', $reminder->project) }}"
                                   class="text-sm text-blue-300 hover:text-blue-100 font-medium truncate transition-colors">
                                    {{ $reminder->project->name }} - {{ $reminder->date }}
                                </a>
                                @if($reminder->recurrence_pattern)
                                    <span class="text-xs text-blue-500 shrink-0">{{ $reminder->recurrence_pattern }}</span>
                                @endif
                            </div>
                            <form method="POST" action="{{ route('projects.reminders.dismiss', [$reminder->project, $reminder]) }}">
                                @csrf
                                <button type="submit" class="text-xs text-blue-400/60 hover:text-blue-200 transition-colors whitespace-nowrap shrink-0">Dismiss</button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Controls bar: view toggle + 24h toggle --}}
            <div class="flex justify-end items-center mb-2" x-data>

                <div class="flex items-center gap-2">
                    {{-- Agenda-only controls: interval dropdown + 24h toggle --}}
                    <div x-show="$store.dayView.current === 'agenda'" x-cloak class="flex items-center gap-2">
                        <select @change="$store.agendaInterval.set($event.target.value)"
                                class="text-sm bg-gray-800 border border-gray-600 rounded px-2 py-1.5 text-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="5"  :selected="$store.agendaInterval.value === 5">5 min</option>
                            <option value="15" :selected="$store.agendaInterval.value === 15">15 min</option>
                            <option value="30" :selected="$store.agendaInterval.value === 30">30 min</option>
                            <option value="60" :selected="$store.agendaInterval.value === 60">1 hr</option>
                        </select>
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
            <div x-cloak x-show="$store.dayView.current === 'agenda'"
                 x-data="taskFilter"
                 data-projects="{{ json_encode($projects) }}"
                 data-tags="{{ json_encode($tags) }}"
                 data-users="{{ json_encode($users) }}"
                 data-locations="{{ json_encode($locations) }}">
                <x-task-input-bar :date="$carbonDate->format('Y-m-d')" :show-filter-and-bulk="false" />
                @include('dashboard.partials.agenda')
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

            {{-- List view --}}
            <div x-cloak x-show="$store.dayView.current === 'list'"
                 x-data="taskFilter"
                 data-projects="{{ json_encode($projects) }}"
                 data-tags="{{ json_encode($tags) }}"
                 data-users="{{ json_encode($users) }}"
                 data-locations="{{ json_encode($locations) }}">
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
                        <select id="sort-select" @change="sortBy($event.target.value)"
                                class="text-sm bg-gray-700 border border-gray-600 rounded px-2 py-1 text-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="date" {{ $sort === 'date' ? 'selected' : '' }}>Date & Time</option>
                            <option value="created" {{ $sort === 'created' ? 'selected' : '' }}>Date Added</option>
                            <option value="name" {{ $sort === 'name' ? 'selected' : '' }}>Name</option>
                            <option value="duration" {{ $sort === 'duration' ? 'selected' : '' }}>Duration</option>
                            <option value="location" {{ $sort === 'location' ? 'selected' : '' }}>Location</option>
                            <option value="custom" {{ $sort === 'custom' ? 'selected' : '' }}>Custom Sort</option>
                        </select>
                        @if($sort !== 'custom')
                        <button @click="toggleSortReversed()"
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
    <script nonce="{{ csp_nonce() }}">
        // ── Export PDF button: navigate preserving current sort/reversed (already
        // in the URL). If the on-page filter is active, snapshot exactly which
        // tasks are currently visible by ID (rather than re-deriving the filter
        // server-side from its raw text) — the server just narrows its own
        // already-authorized/scoped query to that ID set, so there's no second
        // implementation of the filter syntax to keep in sync with the JS one in
        // task-list.blade.php's filterTasks(). The raw filter text still gets
        // sent along, but only for display in the PDF's header meta line.
        document.addEventListener('alpine:init', () => {
            Alpine.data('dayPdfExport', () => ({
                // 'open' etc. are only used by the mobile three-dot menu instance
                // (the desktop button doesn't reference them) — harmless unused
                // state on that instance.
                open: false,
                toggle() { this.open = !this.open; },
                close() { this.open = false; },
                selectPdf() { this.go(); this.close(); },
                go() {
                    // Keeps 'date' (if present) so exporting from a future day's page exports
                    // that day, not today — see DashboardController::exportDayPdf().
                    const p = new URLSearchParams(window.location.search);
                    p.delete('ids[]');

                    const filterText = Alpine.store('taskCount').filterText;
                    if (filterText) {
                        p.set('filter', filterText);
                        const container = document.querySelector('[x-ref="taskContainer"]');
                        const ids = container
                            ? Array.from(container.querySelectorAll('[data-filterable]'))
                                .filter(el => el.style.display !== 'none')
                                .map(el => el.closest('[data-task-group]')?.dataset.taskGroupId)
                                .filter(Boolean)
                            : [];
                        ids.forEach(id => p.append('ids[]', id));
                    } else {
                        p.delete('filter');
                    }

                    window.location.href = '{{ route('day.export-pdf') }}?' + p.toString();
                }
            }));
        });

        // ── Stale-page banner ────────────────────────────────────────────────────
        document.addEventListener('alpine:init', () => {
            Alpine.data('staleBanner', function () { return {
                pageDate: '',
                dayRoute: '',
                stale: false,
                pageDateLabel: '',
                todayLabel: '',
                todayUrl: '',

                init() {
                    this.pageDate = this.$el.dataset.date || '';
                    this.dayRoute = this.$el.dataset.reloadUrl || '';
                    this._check();
                    document.addEventListener('visibilitychange', () => {
                        if (!document.hidden) this._check();
                    });
                    setInterval(() => this._check(), 60_000);
                },

                _check() {
                    const now = new Date();
                    const todayLocal = now.getFullYear() + '-' +
                        String(now.getMonth() + 1).padStart(2, '0') + '-' +
                        String(now.getDate()).padStart(2, '0');
                    this.stale = todayLocal > this.pageDate;
                    if (this.stale) {
                        this.todayUrl  = this.dayRoute + '?date=' + todayLocal;
                        this.pageDateLabel = new Date(this.pageDate + 'T12:00:00')
                            .toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' });
                        this.todayLabel = new Date(todayLocal + 'T12:00:00')
                            .toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' });
                    }
                },
            }; });
        });

        // ── Post-create toast (shown after reload when a stale-page task was added) ──
        document.addEventListener('DOMContentLoaded', () => {
            const todayDate = sessionStorage.getItem('staleTaskCreated');
            if (!todayDate) return;
            sessionStorage.removeItem('staleTaskCreated');

            const todayLabel = new Date(todayDate + 'T12:00:00')
                .toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' });
            const todayUrl = '{{ route('day') }}?date=' + todayDate;

            const toast = document.createElement('div');
            toast.className = 'fixed bottom-4 left-4 z-50 w-80 bg-gray-800 border border-amber-700/50 rounded-lg shadow-2xl overflow-hidden pointer-events-auto';
            toast.innerHTML = `
                <div class="flex items-start gap-3 px-3 py-2.5">
                    <div class="w-5 h-5 rounded-full bg-amber-600 flex-shrink-0 flex items-center justify-center mt-0.5">
                        <svg class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm text-gray-300">Created after midnight — task scheduled for <strong class="text-amber-300">${todayLabel}</strong>.</p>
                        <a href="${todayUrl}" class="text-xs text-amber-400 hover:text-amber-300 underline mt-0.5 inline-block">View today's tasks</a>
                    </div>
                    <button @click="$el.closest('[data-stale-toast]').remove()" class="flex-shrink-0 text-gray-500 hover:text-gray-300 mt-0.5" aria-label="Dismiss">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>`;
            toast.dataset.staleToast = '';
            toast.style.cssText = 'opacity:0;transform:translateY(8px);transition:opacity .2s,transform .2s';
            document.body.appendChild(toast);
            requestAnimationFrame(() => requestAnimationFrame(() => {
                toast.style.opacity = '1';
                toast.style.transform = 'translateY(0)';
            }));
        });



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

            Alpine.store('agendaInterval', {
                value: parseInt(localStorage.getItem('agenda_interval') || '30'),
                set(v) {
                    this.value = parseInt(v, 10);
                    localStorage.setItem('agenda_interval', String(this.value));
                },
            });

            // Each interval slot is always 20px tall; hour height scales accordingly.
            // 60-min → 20px/hr | 30-min → 40px/hr | 15-min → 80px/hr | 5-min → 240px/hr
            Alpine.data('agendaGrid', () => ({
                autoStart: 8,
                autoEnd:   20,

                init() {
                    this.autoStart = parseInt(this.$el.dataset.autoStart || '8');
                    this.autoEnd   = parseInt(this.$el.dataset.autoEnd   || '20');
                    this.$watch('$store.agendaInterval.value', () => this._reposition());
                    this._reposition();
                },

                _hourPx() {
                    return (60 / this.$store.agendaInterval.value) * 20;
                },

                clipOuter() {
                    if (this.$store.agendaFull.on) return '';
                    return `height: ${(this.autoEnd - this.autoStart) * this._hourPx()}px; overflow: hidden;`;
                },

                clipInner() {
                    if (this.$store.agendaFull.on) return '';
                    return `margin-top: -${this.autoStart * this._hourPx()}px;`;
                },

                _reposition() {
                    const hourPx  = this._hourPx();
                    const totalPx = 24 * hourPx;

                    this.$el.querySelectorAll('[data-hour-labels], [data-grid-container]').forEach(el => {
                        el.style.height = totalPx + 'px';
                    });

                    this.$el.querySelectorAll('[data-line-hour]').forEach(el => {
                        el.style.top = (parseInt(el.dataset.lineHour) * hourPx - 8) + 'px';
                    });

                    this.$el.querySelectorAll('[data-line-min]').forEach(el => {
                        el.style.top = (parseInt(el.dataset.lineMin) / 60 * hourPx) + 'px';
                    });

                    this.$el.querySelectorAll('[data-task-start-min]').forEach(el => {
                        const startMin    = parseInt(el.dataset.taskStartMin);
                        const durationMin = parseInt(el.dataset.taskDurationMin);
                        el.style.top    = (startMin / 60 * hourPx) + 'px';
                        el.style.height = Math.max(20, durationMin / 60 * hourPx) + 'px';
                    });

                    const nowEl = this.$el.querySelector('[data-now-min]');
                    if (nowEl) {
                        nowEl.style.top = (parseInt(nowEl.dataset.nowMin) / 60 * hourPx) + 'px';
                    }
                },
            }));
        });
    </script>
    @endpush
</x-app-layout>
