@php
    use Carbon\Carbon;

    $hourHeightPx = 80; // px per hour; 40px per 30-min slot

    // Separate tasks into all-day (no time) and timed
    $allDayTasks  = $tasks->filter(fn($t) => !$t->time);
    $timedTasks   = $tasks->filter(fn($t) => $t->time);

    // Determine grid hour range
    $gridStart = 8;  // default 8 AM
    $gridEnd   = 20; // default 8 PM

    if ($timedTasks->isNotEmpty()) {
        $hours = $timedTasks->map(function ($t) {
            [$h] = explode(':', $t->time);
            return (int) $h;
        });
        $endHours = $timedTasks->map(function ($t) {
            [$h, $m] = explode(':', $t->time);
            $totalMins = (int)$h * 60 + (int)$m + ($t->duration_minutes ?? 30);
            return (int) ceil($totalMins / 60);
        });
        $gridStart = max(0,  min($hours->min() - 1, $gridStart));
        $gridEnd   = min(24, max($endHours->max() + 1, $gridEnd));
    }

    $totalHours    = $gridEnd - $gridStart;
    $gridHeightPx  = $totalHours * $hourHeightPx;

    // Current time for today indicator
    $isToday       = $carbonDate->isToday();
    $nowMinutes    = $isToday ? (Carbon::now()->hour * 60 + Carbon::now()->minute) : null;
    $nowTopPx      = $isToday
        ? (($nowMinutes - $gridStart * 60) / 60 * $hourHeightPx)
        : null;

    // Helper: snap minutes to nearest 30
    $snap = fn(int $m): int => (int) (round($m / 30) * 30);

    // Helper: 24h int to label e.g. 9 → "9 AM", 13 → "1 PM"
    $hourLabel = function (int $h): string {
        if ($h === 0)  return '12 AM';
        if ($h === 12) return '12 PM';
        return $h < 12 ? "{$h} AM" : ($h - 12) . ' PM';
    };
@endphp

<div class="bg-[#181818] rounded-lg border border-gray-700 overflow-hidden">

    {{-- All-day strip --}}
    @if($allDayTasks->isNotEmpty())
    <div class="border-b border-gray-700 flex">
        <div class="w-16 flex-shrink-0 px-2 py-2 text-right">
            <span class="text-xs text-gray-500">all-day</span>
        </div>
        <div class="flex-1 border-l border-gray-700 p-2 flex flex-wrap gap-1">
            @foreach($allDayTasks as $task)
                <a href="{{ route('tasks.show', $task) }}"
                   class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-gray-700 hover:bg-gray-600 text-gray-200 transition-colors max-w-xs truncate">
                    {{ $task->name }}
                </a>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Time grid --}}
    <div class="flex">

        {{-- Hour labels column --}}
        <div class="w-16 flex-shrink-0 relative" style="height: {{ $gridHeightPx }}px">
            @for($h = $gridStart; $h <= $gridEnd; $h++)
                @if($h > $gridStart)
                <div class="absolute right-2 text-xs text-gray-500 select-none"
                     style="top: {{ ($h - $gridStart) * $hourHeightPx - 8 }}px">
                    {{ $hourLabel($h) }}
                </div>
                @endif
            @endfor
        </div>

        {{-- Grid lines + task blocks --}}
        <div class="relative flex-1 border-l border-gray-700" style="height: {{ $gridHeightPx }}px">

            {{-- Hour and half-hour grid lines --}}
            @for($h = $gridStart; $h <= $gridEnd; $h++)
                <div class="absolute w-full border-t border-gray-700"
                     style="top: {{ ($h - $gridStart) * $hourHeightPx }}px"></div>
                @if($h < $gridEnd)
                <div class="absolute w-full border-t border-gray-800"
                     style="top: {{ ($h - $gridStart) * $hourHeightPx + ($hourHeightPx / 2) }}px"></div>
                @endif
            @endfor

            {{-- Current time indicator (today only) --}}
            @if($isToday && $nowTopPx !== null && $nowTopPx >= 0 && $nowTopPx <= $gridHeightPx)
            <div class="absolute w-full flex items-center pointer-events-none z-20"
                 style="top: {{ $nowTopPx }}px">
                <div class="w-2 h-2 rounded-full bg-red-500 -ml-1 flex-shrink-0"></div>
                <div class="flex-1 border-t-2 border-red-500"></div>
            </div>
            @endif

            {{-- Task blocks --}}
            @foreach($timedTasks as $task)
                @php
                    [$th, $tm] = array_map('intval', explode(':', $task->time));

                    // Snap start time to nearest 30 minutes
                    $snappedM = $snap($tm);
                    $snappedH = $th;
                    if ($snappedM === 60) { $snappedH++; $snappedM = 0; }

                    // Display duration: minimum 30 min
                    $displayDuration = max($task->duration_minutes ?? 30, 30);

                    // Top offset in px
                    $topPx = (($snappedH - $gridStart) * 60 + $snappedM) / 60 * $hourHeightPx;

                    // Height in px
                    $heightPx = ($displayDuration / 60) * $hourHeightPx;

                    // Clamp to grid
                    $topPx    = max(0, $topPx);
                    $heightPx = max(20, $heightPx);

                    // Time label for block (actual stored time, not snapped)
                    $amPm  = $th < 12 ? 'AM' : 'PM';
                    $h12   = $th % 12 ?: 12;
                    $mPad  = str_pad($tm, 2, '0', STR_PAD_LEFT);
                    $timeLabel = "{$h12}:{$mPad} {$amPm}";
                    if ($task->duration_minutes) {
                        $endMins = $th * 60 + $tm + $task->duration_minutes;
                        $endH = intdiv($endMins, 60) % 24;
                        $endM = $endMins % 60;
                        $endAmPm = $endH < 12 ? 'AM' : 'PM';
                        $endH12  = $endH % 12 ?: 12;
                        $endMPad = str_pad($endM, 2, '0', STR_PAD_LEFT);
                        $timeLabel .= " – {$endH12}:{$endMPad} {$endAmPm}";
                    }

                    // Pick a block color based on project (cycle through a palette if no project)
                    $blockColors = [
                        'bg-blue-700 hover:bg-blue-600 border-blue-500',
                        'bg-indigo-700 hover:bg-indigo-600 border-indigo-500',
                        'bg-violet-700 hover:bg-violet-600 border-violet-500',
                        'bg-teal-700 hover:bg-teal-600 border-teal-500',
                        'bg-cyan-700 hover:bg-cyan-600 border-cyan-500',
                    ];
                    $colorClass = $blockColors[$task->id % count($blockColors)];
                @endphp

                <a href="{{ route('tasks.show', $task) }}"
                   class="absolute left-1 right-1 rounded border-l-2 text-white transition-colors overflow-hidden z-10 {{ $colorClass }}"
                   style="top: {{ $topPx }}px; height: {{ $heightPx }}px;"
                   title="{{ $task->name }} ({{ $timeLabel }})">
                    <div class="px-2 py-1 h-full flex flex-col justify-start overflow-hidden">
                        <div class="text-xs font-semibold leading-tight truncate">{{ $task->name }}</div>
                        @if($heightPx >= 40)
                        <div class="text-xs opacity-75 leading-tight truncate mt-0.5">{{ $timeLabel }}</div>
                        @endif
                        @if($heightPx >= 60 && $task->description)
                        <div class="text-xs opacity-60 leading-tight truncate mt-0.5">{{ $task->description }}</div>
                        @endif
                    </div>
                </a>
            @endforeach

        </div>{{-- end grid area --}}
    </div>{{-- end flex --}}
</div>
