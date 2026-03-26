@php
    use Carbon\Carbon;

    $hourHeightPx = 80; // px per hour; 40px per 30-min slot

    // Separate tasks into all-day (no time) and timed
    $allDayTasks  = $tasks->filter(fn($t) => !$t->time);
    $timedTasks   = $tasks->filter(fn($t) => $t->time);

    // Abbreviated ("auto") range — what to clip to when not showing full day
    $autoStart = 8;  // default 8 AM
    $autoEnd   = 20; // default 8 PM

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
        $autoStart = max(0,  min($hours->min() - 1, $autoStart));
        $autoEnd   = min(24, max($endHours->max() + 1, $autoEnd));
    }

    // Always render the full 24-hour grid so the toggle works client-side
    $gridStart    = 0;
    $gridEnd      = 24;
    $totalHours   = 24;
    $gridHeightPx = $totalHours * $hourHeightPx;

    // Clip values (px) for abbreviated view — passed to Alpine
    $clipTopPx    = $autoStart * $hourHeightPx;
    $clipHeightPx = ($autoEnd - $autoStart) * $hourHeightPx;

    // Current time indicator — position from midnight
    $isToday  = $carbonDate->isToday();
    $nowTopPx = $isToday
        ? ((Carbon::now()->hour * 60 + Carbon::now()->minute) / 60 * $hourHeightPx)
        : null;

    // Helper: snap minutes to nearest 30; returns [snappedH, snappedM]
    $snapTime = function (int $h, int $m) {
        $snappedM = (int) (round($m / 30) * 30);
        if ($snappedM === 60) { $h++; $snappedM = 0; }
        return [$h, $snappedM];
    };

    // Helper: 24h int to label e.g. 9 → "9 AM", 13 → "1 PM"
    $hourLabel = function (int $h): string {
        if ($h === 0)  return '12 AM';
        if ($h === 12) return '12 PM';
        return $h < 12 ? "{$h} AM" : ($h - 12) . ' PM';
    };

    // ── Collision layout ──────────────────────────────────────────────────────
    // Task positions are always computed from midnight (hour 0).
    $slotGroups = []; // "H:M" => [task, ...]
    foreach ($timedTasks as $task) {
        [$th, $tm] = array_map('intval', explode(':', $task->time));
        [$sh, $sm] = $snapTime($th, $tm);
        $key = "{$sh}:{$sm}";
        $slotGroups[$key][] = $task;
    }

    $taskLayout = []; // taskId => [topPx, heightPx, colIndex, colCount]
    foreach ($slotGroups as $key => $group) {
        [$sh, $sm] = array_map('intval', explode(':', $key));
        $colCount = count($group);
        $topPx    = ($sh * 60 + $sm) / 60 * $hourHeightPx;

        foreach ($group as $colIndex => $task) {
            $displayDuration = max($task->duration_minutes ?? 30, 30);
            $heightPx = max(20, ($displayDuration / 60) * $hourHeightPx);

            $taskLayout[$task->id] = [
                'topPx'    => $topPx,
                'heightPx' => $heightPx,
                'colIndex' => $colIndex,
                'colCount' => $colCount,
            ];
        }
    }
    // ─────────────────────────────────────────────────────────────────────────

    $blockColors = [
        'bg-blue-700 hover:bg-blue-600 border-blue-500',
        'bg-indigo-700 hover:bg-indigo-600 border-indigo-500',
        'bg-violet-700 hover:bg-violet-600 border-violet-500',
        'bg-teal-700 hover:bg-teal-600 border-teal-500',
        'bg-cyan-700 hover:bg-cyan-600 border-cyan-500',
    ];
@endphp

@pushOnce('scripts')
<script>
    window.agendaQuickComplete = function () {
        return {
            done: false,
            loading: false,
            async submit() {
                this.loading = true;
                const form = this.$el;
                try {
                    const res = await fetch(form.action, {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        body: new FormData(form),
                    });
                    const data = await res.json().catch(() => ({}));
                    if (res.ok && data.ok !== false) {
                        this.done = true;
                        // Brief pause to show the filled dot, then fade out the block
                        await new Promise(r => setTimeout(r, 400));
                        const block = form.closest('[data-task-block]');
                        if (block) {
                            block.style.transition = 'opacity 0.3s';
                            block.style.opacity = '0';
                            setTimeout(() => block.style.display = 'none', 300);
                        }
                    } else {
                        alert('Could not complete task: ' + (data.message || 'Please try again.'));
                    }
                } catch {
                    form.submit(); // network failure – fall back to full reload
                } finally {
                    this.loading = false;
                }
            }
        };
    };
</script>
@endPushOnce

<div class="bg-[#181818] rounded-lg border border-gray-700 overflow-hidden">

    {{-- All-day strip --}}
    @if($allDayTasks->isNotEmpty())
    <div class="border-b border-gray-700 flex">
        <div class="w-16 flex-shrink-0 px-2 py-2 text-right">
            <span class="text-xs text-gray-500">all-day</span>
        </div>
        <div class="flex-1 border-l border-gray-700 p-2 flex flex-wrap gap-1">
            @foreach($allDayTasks as $task)
                <span data-task-block class="inline-flex items-center gap-1.5 px-2 py-1 rounded bg-gray-700 hover:bg-gray-600 transition-colors max-w-xs">
                    @if($task->status === 'done')
                        <span class="w-3 h-3 rounded-full bg-green-600 flex-shrink-0" title="Completed"></span>
                    @else
                        <form x-data="agendaQuickComplete()" @submit.prevent="submit()"
                              method="POST" action="{{ route('tasks.update', $task) }}" class="flex-shrink-0">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="status" value="done">
                            <input type="hidden" name="name" value="{{ $task->name }}">
                            <input type="hidden" name="description" value="{{ $task->description }}">
                            <input type="hidden" name="date" value="{{ $task->date }}">
                            <input type="hidden" name="time" value="{{ $task->time }}">
                            <input type="hidden" name="project_id" value="{{ $task->project_id }}">
                            <input type="hidden" name="parent_id" value="{{ $task->parent_id }}">
                            <input type="hidden" name="recurrence_pattern" value="{{ $task->recurrence_pattern }}">
                            @foreach($task->tags as $tag)
                                <input type="hidden" name="tag_ids[]" value="{{ $tag->id }}">
                            @endforeach
                            @foreach($task->assignees as $assignee)
                                <input type="hidden" name="assignee_ids[]" value="{{ $assignee->id }}">
                            @endforeach
                            <input type="hidden" name="quick_complete" value="1">
                            <button x-show="!done" type="submit"
                                    :disabled="loading" :class="loading ? 'opacity-40 cursor-wait' : ''"
                                    class="w-3 h-3 rounded-full border {{ $task->recurrence_pattern ? 'border-purple-400 hover:border-purple-300' : 'border-gray-400 hover:border-green-400' }} hover:bg-green-400 hover:bg-opacity-20 transition"
                                    title="{{ $task->recurrence_pattern ? 'Complete & create next (' . $task->recurrence_pattern . ')' : 'Mark as done' }}">
                            </button>
                            <span x-show="done" class="w-3 h-3 rounded-full bg-green-600 block" style="display:none"></span>
                        </form>
                    @endif
                    <a href="{{ route('tasks.show', $task) }}"
                       class="text-xs font-medium text-gray-200 truncate">{{ $task->name }}</a>
                </span>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Time grid — clips to auto range unless full-day mode is on --}}
    <div x-data="{
            clipTopPx: {{ $clipTopPx }},
            clipHeightPx: {{ $clipHeightPx }},
        }"
         :style="$store.agendaFull.on ? '' : `height: ${clipHeightPx}px; overflow: hidden;`">
    <div :style="$store.agendaFull.on ? '' : `margin-top: -${clipTopPx}px;`">
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
                    $layout   = $taskLayout[$task->id];
                    $topPx    = $layout['topPx'];
                    $heightPx = $layout['heightPx'];
                    $colIndex = $layout['colIndex'];
                    $colCount = $layout['colCount'];

                    // Horizontal slice: each column gets equal share of width
                    $leftPct  = $colIndex / $colCount * 100;
                    $widthPct = 100 / $colCount;

                    // Time label (actual stored time, not snapped)
                    [$th, $tm] = array_map('intval', explode(':', $task->time));
                    $amPm  = $th < 12 ? 'AM' : 'PM';
                    $h12   = $th % 12 ?: 12;
                    $mPad  = str_pad($tm, 2, '0', STR_PAD_LEFT);
                    $timeLabel = "{$h12}:{$mPad} {$amPm}";
                    if ($task->duration_minutes) {
                        $endMins = $th * 60 + $tm + $task->duration_minutes;
                        $endH    = intdiv($endMins, 60) % 24;
                        $endM    = $endMins % 60;
                        $endAmPm = $endH < 12 ? 'AM' : 'PM';
                        $endH12  = $endH % 12 ?: 12;
                        $endMPad = str_pad($endM, 2, '0', STR_PAD_LEFT);
                        $timeLabel .= " – {$endH12}:{$endMPad} {$endAmPm}";
                    }

                    $colorClass = $blockColors[$task->id % count($blockColors)];
                @endphp

                <div data-task-block class="absolute rounded border-l-2 text-white overflow-hidden z-10 {{ $colorClass }}"
                     style="top: {{ $topPx }}px; height: {{ $heightPx }}px; left: calc({{ $leftPct }}% + 2px); width: calc({{ $widthPct }}% - 4px);">
                    {{-- Quick complete button --}}
                    @if($task->status === 'done')
                        <div class="absolute top-1 right-1 w-3.5 h-3.5 rounded-full bg-green-400 z-20 flex-shrink-0" title="Completed"></div>
                    @else
                        <form x-data="agendaQuickComplete()" @submit.prevent="submit()"
                              method="POST" action="{{ route('tasks.update', $task) }}"
                              class="absolute top-1 right-1 z-20">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="status" value="done">
                            <input type="hidden" name="name" value="{{ $task->name }}">
                            <input type="hidden" name="description" value="{{ $task->description }}">
                            <input type="hidden" name="date" value="{{ $task->date }}">
                            <input type="hidden" name="time" value="{{ $task->time }}">
                            <input type="hidden" name="project_id" value="{{ $task->project_id }}">
                            <input type="hidden" name="parent_id" value="{{ $task->parent_id }}">
                            <input type="hidden" name="recurrence_pattern" value="{{ $task->recurrence_pattern }}">
                            @foreach($task->tags as $tag)
                                <input type="hidden" name="tag_ids[]" value="{{ $tag->id }}">
                            @endforeach
                            @foreach($task->assignees as $assignee)
                                <input type="hidden" name="assignee_ids[]" value="{{ $assignee->id }}">
                            @endforeach
                            <input type="hidden" name="quick_complete" value="1">
                            <button x-show="!done" type="submit"
                                    :disabled="loading" :class="loading ? 'opacity-40 cursor-wait' : ''"
                                    class="w-3.5 h-3.5 rounded-full border-2 {{ $task->recurrence_pattern ? 'border-purple-300 hover:border-purple-100' : 'border-white border-opacity-70 hover:border-green-300' }} hover:bg-green-400 hover:bg-opacity-30 transition block"
                                    title="{{ $task->recurrence_pattern ? 'Complete & create next (' . $task->recurrence_pattern . ')' : 'Mark as done' }}">
                            </button>
                            <div x-show="done" class="w-3.5 h-3.5 rounded-full bg-green-400" style="display:none" title="Completed"></div>
                        </form>
                    @endif
                    {{-- Clickable link area --}}
                    <a href="{{ route('tasks.show', $task) }}"
                       class="block h-full px-2 py-1 pr-6"
                       title="{{ $task->name }} ({{ $timeLabel }})">
                        <div class="flex flex-col justify-start overflow-hidden h-full">
                            <div class="text-xs font-semibold leading-tight truncate">{{ $task->name }}</div>
                            @if($heightPx >= 40)
                            <div class="text-xs opacity-75 leading-tight truncate mt-0.5">{{ $timeLabel }}</div>
                            @endif
                            @if($heightPx >= 60 && $task->description)
                            <div class="text-xs opacity-60 leading-tight truncate mt-0.5">{{ $task->description }}</div>
                            @endif
                        </div>
                    </a>
                </div>
            @endforeach

        </div>{{-- end grid area --}}
    </div>{{-- end flex --}}
    </div>{{-- end inner shift wrapper --}}
    </div>{{-- end clip wrapper --}}
</div>
