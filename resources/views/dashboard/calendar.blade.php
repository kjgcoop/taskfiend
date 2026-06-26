<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-100 leading-tight">
                {{ __('By Date') }} - {{ $startDate->format('F Y') }}
            </h2>
            <div class="flex gap-2">
                <a href="{{ route('calendar', ['month' => $month == 1 ? 12 : $month - 1, 'year' => $month == 1 ? $year - 1 : $year]) }}" class="px-3 py-2 bg-gray-700 text-gray-300 rounded hover:bg-gray-600">
                    &larr; Previous
                </a>
                <a href="{{ route('calendar', ['month' => $month == 12 ? 1 : $month + 1, 'year' => $month == 12 ? $year + 1 : $year]) }}" class="px-3 py-2 bg-gray-700 text-gray-300 rounded hover:bg-gray-600">
                    Next &rarr;
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12" x-data>
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="flex gap-3 mb-4">
                <a href="{{ route('overdue') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-[#202020] border border-gray-600 text-gray-400 hover:bg-gray-700 rounded-md text-sm transition">
                    Overdue
                    <span class="inline-flex items-center justify-center px-2 py-0.5 text-xs font-bold bg-gray-700 text-gray-400 rounded-full">{{ $overdueCount }}</span>
                </a>
                <a href="{{ route('undated') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-[#202020] border border-gray-600 text-gray-400 hover:bg-gray-700 rounded-md text-sm transition">
                    No Date
                    <span class="inline-flex items-center justify-center px-2 py-0.5 text-xs font-bold bg-gray-700 text-gray-400 rounded-full">{{ $undatedCount }}</span>
                </a>
            </div>
            <div class="bg-[#202020] border border-gray-700 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="grid grid-cols-7 gap-2">
                        @foreach(['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $day)
                            <div class="text-center font-semibold text-gray-300 p-2">{{ $day }}</div>
                        @endforeach

                        @php
                            $date = $startDate->copy()->startOfWeek(\Carbon\Carbon::SUNDAY);
                            $endOfMonth = $startDate->copy()->endOfMonth();
                        @endphp

                        @for($i = 0; $i < 42; $i++)
                            <div class="border rounded p-2 min-h-24 {{ $date->isToday() ? 'border-blue-500 bg-blue-950/20' : ($date->month != $month ? 'border-gray-600 bg-[#101010] text-gray-500' : 'border-gray-600 bg-[#202020]') }}"
                                 data-cal-cell="{{ $date->format('Y-m-d') }}">
                                @php
                                    $dateKey = $date->format('Y-m-d');
                                    $dayTasks = $tasks->get($dateKey) ?? collect();
                                @endphp
                                <div class="flex items-center justify-between mb-1">
                                    <a href="{{ route('day', ['date' => $dateKey]) }}" class="{{ $date->isToday() ? 'flex items-center justify-center w-6 h-6 bg-blue-600 text-white rounded-full text-xs leading-none font-bold' : 'font-semibold text-sm text-gray-300 hover:text-blue-400 hover:underline' }}">
                                        {{ $date->day }}
                                    </a>
                                    @if($dayTasks->count() > 0)
                                        <span class="font-thin text-[10px] text-gray-500" data-cal-count>{{ $dayTasks->count() }}</span>
                                    @endif
                                </div>
                                <div class="space-y-1 overflow-hidden">
                                    @foreach($dayTasks->take(3) as $task)
                                        <a href="{{ route('tasks.show', $task) }}"
                                           data-task-group-id="{{ $task->id }}"
                                           @click.prevent="($event.ctrlKey || $event.metaKey) ? window.open('{{ route('tasks.show', $task) }}', '_blank') : $dispatch('open-task-panel', { taskId: {{ $task->id }} })"
                                           class="block w-full text-left text-xs p-1 bg-blue-900 text-blue-200 rounded truncate hover:bg-blue-800 task-title">
                                            {!! render_title($task->name) !!}
                                        </a>
                                    @endforeach
                                    @if($dayTasks->count() > 3)
                                        <a href="{{ route('day', ['date' => $dateKey]) }}" class="block text-xs text-blue-400 hover:underline">
                                            +{{ $dayTasks->count() - 3 }} more
                                        </a>
                                    @endif
                                </div>
                            </div>
                            @php $date->addDay(); @endphp
                        @endfor
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script nonce="{{ csp_nonce() }}">
        window.addEventListener('task-panel-updated', (e) => {
            const d = e.detail;
            const chip = document.querySelector(`[data-task-group-id="${d.id}"]`);
            if (!chip) return;

            const oldCell = chip.closest('[data-cal-cell]');
            const dateChanged = oldCell && d.date !== oldCell.dataset.calCell;
            const shouldRemove = d.inactive || dateChanged;

            if (!shouldRemove) {
                chip.textContent = d.name;
                return;
            }

            // Fade chip out of old cell
            chip.style.transition = 'opacity 0.4s';
            chip.style.opacity = '0';

            setTimeout(() => {
                chip.remove();

                // Decrement old cell count
                if (oldCell) {
                    const countEl = oldCell.querySelector('[data-cal-count]');
                    if (countEl) {
                        const n = parseInt(countEl.textContent) - 1;
                        n > 0 ? (countEl.textContent = n) : countEl.remove();
                    }
                }

                // Add to new cell if it's visible on this calendar and task isn't inactive
                if (!d.inactive && d.date) {
                    const newCell = document.querySelector(`[data-cal-cell="${d.date}"]`);
                    if (newCell) addChipToCell(newCell, d);
                }
            }, 400);
        });

        function addChipToCell(cell, d) {
            // Update or create the tiny task count in the cell header
            let countEl = cell.querySelector('[data-cal-count]');
            if (countEl) {
                countEl.textContent = parseInt(countEl.textContent) + 1;
            } else {
                countEl = document.createElement('span');
                countEl.className = 'font-thin text-[10px] text-gray-500';
                countEl.setAttribute('data-cal-count', '');
                countEl.textContent = '1';
                cell.querySelector('.flex.items-center').appendChild(countEl);
            }

            const container = cell.querySelector('.space-y-1');
            const visibleChips = container.querySelectorAll('[data-task-group-id]');
            // The "+N more" link is the only <a> inside .space-y-1
            const moreLink = container.querySelector('a');

            if (visibleChips.length < 3) {
                // Room for another chip — insert it before "+N more" (or append)
                const btn = document.createElement('a');
                btn.href = d.url;
                btn.dataset.taskGroupId = d.id;
                btn.addEventListener('click', (e) => { if (!(e.ctrlKey || e.metaKey)) { e.preventDefault(); window.dispatchEvent(new CustomEvent('open-task-panel', { detail: { taskId: d.id } })); } });
                btn.className = 'block w-full text-left text-xs p-1 bg-blue-900 text-blue-200 rounded truncate hover:bg-blue-800';
                btn.textContent = d.name;
                btn.style.opacity = '0';
                btn.style.transition = 'opacity 0.4s';
                moreLink ? container.insertBefore(btn, moreLink) : container.appendChild(btn);
                // Fade in on next frame
                requestAnimationFrame(() => requestAnimationFrame(() => { btn.style.opacity = '1'; }));
            } else {
                // Already at 3 chips — bump (or create) "+N more"
                if (moreLink) {
                    const m = moreLink.textContent.match(/\+(\d+)/);
                    if (m) moreLink.textContent = `+${parseInt(m[1]) + 1} more`;
                } else {
                    const dayLink = cell.querySelector('a'); // date-number link in header
                    const a = document.createElement('a');
                    a.href = dayLink ? dayLink.href : '#';
                    a.className = 'block text-xs text-blue-400 hover:underline';
                    a.textContent = '+1 more';
                    container.appendChild(a);
                }
            }
        }
    </script>
</x-app-layout>
