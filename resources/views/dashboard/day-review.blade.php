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
                <h2 class="font-semibold text-xl text-gray-100 leading-tight">
                    {{ $carbonDate->format('l, F j, Y') }}
                    <span class="text-sm text-gray-500 font-normal ml-1">Review</span>
                </h2>
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
                $totalOnPlate = $completedOnDay->count() + $completedLater->count() + $stillOpen->count() + $rescheduledTasks->count();
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
                                    'task'     => $task,
                                    'dotColor' => '',
                                    'dotTitle' => 'Not completed',
                                    'dimName'  => false,
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
