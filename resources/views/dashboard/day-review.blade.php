<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div class="flex items-center gap-3">
                <a href="{{ route('day') }}?date={{ $carbonDate->copy()->subDay()->format('Y-m-d') }}"
                   class="text-gray-400 hover:text-gray-100"
                   title="{{ $carbonDate->copy()->subDay()->format('l, F j, Y') }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                    </svg>
                </a>
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
                        {{ $carbonDate->format('l, F j, Y') }}
                        <span class="text-sm text-gray-500 font-normal ml-1">Review</span>
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
                        <label class="text-gray-400 hover:text-gray-100 cursor-pointer" title="Pick from calendar" @click.stop>
                            <input type="date" class="sr-only" :value="currentDate" @change="pickDate($event.target.value)" />
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </label>
                        <span x-show="error" class="text-red-400 text-xs">Can't parse date</span>
                    </div>
                </div>
                @if($carbonDate->copy()->addDay()->lt(today()))
                    <a href="{{ route('day') }}?date={{ $carbonDate->copy()->addDay()->format('Y-m-d') }}"
                       class="text-gray-400 hover:text-gray-100"
                       title="{{ $carbonDate->copy()->addDay()->format('l, F j, Y') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </a>
                @else
                    <a href="{{ route('day') }}"
                       class="text-gray-400 hover:text-gray-100"
                       title="Today">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-8">

            @php
                $totalOnPlate = $completedOnDay->count() + $completedLater->count() + $stillOpen->count() + $archivedOnDay->count() + $rescheduledTasks->count();
                $totalCompleted = $completedOnDay->count() + $completedLater->count();
                $isEmpty = $totalOnPlate === 0 && $assignedThatDayTasks->count() === 0;
            @endphp

            @if($isEmpty)
                <div class="bg-[#202020] p-10 rounded-lg text-center border border-gray-700">
                    <p class="text-gray-400">Nothing was on your plate this day.</p>
                </div>
            @else

                {{-- Summary pill --}}
                @if($totalOnPlate > 0)
                    <div class="flex items-center gap-2 text-sm text-gray-500">
                        <span>{{ $totalCompleted }} of {{ $totalOnPlate }} tasks completed</span>
                        @if($totalOnPlate > 0)
                            <div class="flex-1 max-w-xs h-1.5 bg-gray-700 rounded-full overflow-hidden">
                                <div class="h-full bg-green-600 rounded-full"
                                     style="width: {{ $totalOnPlate > 0 ? round($totalCompleted / $totalOnPlate * 100) : 0 }}%">
                                </div>
                            </div>
                        @endif
                    </div>
                @endif

                {{-- ── Completed ────────────────────────────────────────────── --}}
                @if($completedOnDay->count() > 0 || $completedLater->count() > 0)
                    <section>
                        <h3 class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-3">
                            Completed
                        </h3>
                        <div class="space-y-2">
                            @foreach($completedOnDay as $task)
                                @include('dashboard.partials.review-task-row', [
                                    'task'       => $task,
                                    'dotColor'   => 'bg-green-600',
                                    'dotTitle'   => 'Completed this day',
                                    'dimName'    => true,
                                ])
                            @endforeach
                            @foreach($completedLater as $task)
                                @php
                                    $completedAt = $task->completed_at ? \Carbon\Carbon::parse($task->completed_at) : null;
                                    $completedLabel = $completedAt ? 'Completed ' . $completedAt->format('l, F j, Y') : 'Completed later';
                                @endphp
                                @include('dashboard.partials.review-task-row', [
                                    'task'       => $task,
                                    'dotColor'   => 'bg-gray-500',
                                    'dotTitle'   => $completedLabel,
                                    'dimName'    => true,
                                    'subtitle'   => $completedLabel,
                                ])
                            @endforeach
                        </div>
                    </section>
                @endif

                {{-- ── Still Open ───────────────────────────────────────────── --}}
                @if($stillOpen->count() > 0)
                    <section>
                        <h3 class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-3">
                            Still Open
                        </h3>
                        <div class="space-y-2">
                            @foreach($stillOpen as $task)
                                @include('dashboard.partials.review-task-row', [
                                    'task'        => $task,
                                    'dotColor'    => '',
                                    'dotTitle'    => 'Not completed',
                                    'dimName'     => false,
                                    'completable' => true,
                                ])
                            @endforeach
                        </div>
                    </section>
                @endif

                {{-- ── Archived ─────────────────────────────────────────────── --}}
                @if($archivedOnDay->count() > 0)
                    <section>
                        <h3 class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-3">
                            Archived
                        </h3>
                        <div class="space-y-2">
                            @foreach($archivedOnDay as $task)
                                @php
                                    $archivedSubtitle = $task->project && in_array($task->project->status, ['archived', 'done'])
                                        ? 'Project archived: ' . $task->project->name
                                        : 'Archived';
                                @endphp
                                @include('dashboard.partials.review-task-row', [
                                    'task'     => $task,
                                    'dotColor' => 'bg-gray-600',
                                    'dotTitle' => $archivedSubtitle,
                                    'dimName'  => true,
                                    'subtitle' => $archivedSubtitle,
                                ])
                            @endforeach
                        </div>
                    </section>
                @endif

                {{-- ── Rescheduled ──────────────────────────────────────────── --}}
                @if($rescheduledTasks->count() > 0)
                    <section>
                        <h3 class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-3">
                            Rescheduled
                        </h3>
                        <div class="space-y-2">
                            @foreach($rescheduledTasks as $task)
                                @php
                                    $newDate = $task->rescheduled_to
                                        ? \Carbon\Carbon::parse($task->rescheduled_to)->format('l, F j')
                                        : null;
                                    $subtitle = $newDate ? '→ ' . $newDate : '→ date removed';
                                @endphp
                                @include('dashboard.partials.review-task-row', [
                                    'task'     => $task,
                                    'dotColor' => 'bg-amber-600',
                                    'dotTitle' => $subtitle,
                                    'dimName'  => false,
                                    'subtitle' => $subtitle,
                                ])
                            @endforeach
                        </div>
                    </section>
                @endif

                {{-- ── Assigned to you that day ─────────────────────────────── --}}
                @if($assignedThatDayTasks->count() > 0)
                    <section>
                        <h3 class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-3">
                            Assigned to You That Day
                        </h3>
                        <div class="space-y-2">
                            @foreach($assignedThatDayTasks as $task)
                                @php
                                    $assignedDot   = $task->status === 'done' ? 'bg-green-600' : '';
                                    $assignedTitle = $task->status === 'done' ? 'Completed' : 'Not completed';
                                @endphp
                                @include('dashboard.partials.review-task-row', [
                                    'task'       => $task,
                                    'dotColor'   => $assignedDot,
                                    'dotTitle'   => $assignedTitle,
                                    'dimName'    => $task->status === 'done',
                                    'showCreator'=> true,
                                ])
                            @endforeach
                        </div>
                    </section>
                @endif

            @endif
        </div>
    </div>
</x-app-layout>
