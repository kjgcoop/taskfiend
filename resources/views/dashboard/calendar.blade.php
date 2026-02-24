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

    <div class="py-12">
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
                            $date = $startDate->copy()->startOfWeek();
                            $endOfMonth = $startDate->copy()->endOfMonth();
                        @endphp

                        @for($i = 0; $i < 42; $i++)
                            <div class="border border-gray-600 rounded p-2 min-h-24 {{ $date->month != $month ? 'bg-[#101010] text-gray-500' : 'bg-[#202020]' }}"
                                 data-cal-cell="{{ $date->format('Y-m-d') }}">
                                @php
                                    $dateKey = $date->format('Y-m-d');
                                    $dayTasks = $tasks->get($dateKey) ?? collect();
                                @endphp
                                <div class="flex items-center justify-between mb-1">
                                    <a href="{{ route('day', ['date' => $dateKey]) }}" class="font-semibold text-sm text-gray-300 hover:text-blue-400 hover:underline">
                                        {{ $date->day }}
                                    </a>
                                    @if($dayTasks->count() > 0)
                                        <span class="font-thin text-[10px] text-gray-500" data-cal-count>{{ $dayTasks->count() }}</span>
                                    @endif
                                </div>
                                <div class="space-y-1 overflow-hidden">
                                    @foreach($dayTasks->take(3) as $task)
                                        <button type="button"
                                                data-task-group-id="{{ $task->id }}"
                                                onclick="openTaskPanel({{ $task->id }})"
                                                class="block w-full text-left text-xs p-1 bg-blue-900 text-blue-200 rounded truncate hover:bg-blue-800">
                                            {{ $task->name }}
                                        </button>
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

    <script>
        window.addEventListener('task-panel-updated', (e) => {
            const d = e.detail;
            const chip = document.querySelector(`[data-task-group-id="${d.id}"]`);
            if (!chip) return;

            const cell = chip.closest('[data-cal-cell]');
            const shouldFade = d.inactive || (cell && d.date !== cell.dataset.calCell);

            if (shouldFade) {
                chip.style.transition = 'opacity 0.4s';
                chip.style.opacity = '0';
                setTimeout(() => {
                    chip.remove();
                    if (cell) {
                        const countEl = cell.querySelector('[data-cal-count]');
                        if (countEl) {
                            const n = parseInt(countEl.textContent) - 1;
                            n > 0 ? (countEl.textContent = n) : countEl.remove();
                        }
                    }
                }, 400);
            } else {
                // Name updated — refresh the chip label
                chip.textContent = d.name;
            }
        });
    </script>
</x-app-layout>
