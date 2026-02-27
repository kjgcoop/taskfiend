@php
    // Defaults
    $dotColor    = $dotColor    ?? '';
    $dotTitle    = $dotTitle    ?? '';
    $dimName     = $dimName     ?? false;
    $subtitle    = $subtitle    ?? null;
    $showCreator = $showCreator ?? false;
    $completable = $completable ?? false;
@endphp

<div class="bg-[#202020] px-4 py-3 rounded-lg border border-gray-700 flex items-start gap-3">

    {{-- Status dot / quick-complete button --}}
    @if($completable && $task->status !== 'done')
        <form method="POST" action="{{ route('tasks.update', $task) }}" class="flex-shrink-0 mt-0.5">
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
            <button type="submit"
                    class="w-5 h-5 rounded-full border-2 border-gray-600 hover:border-green-400 hover:bg-green-400 hover:bg-opacity-20 transition"
                    title="Mark as done"></button>
        </form>
    @else
        <div class="mt-0.5 flex-shrink-0 w-5 h-5 rounded-full flex items-center justify-center
                    {{ $dotColor ?: 'border-2 border-gray-600' }}"
             title="{{ $dotTitle }}">
        </div>
    @endif

    {{-- Content --}}
    <div class="flex-1 min-w-0">
        <div class="flex items-start justify-between gap-2">
            <a href="{{ route('tasks.show', $task) }}"
               class="font-medium leading-snug hover:underline {{ $dimName ? 'text-gray-500 line-through' : 'text-gray-100' }}">
                {{ $task->name }}
            </a>

            {{-- Assignee avatars --}}
            @if($task->assignees->count() > 0)
                @php $avatarColors = ['bg-blue-500','bg-green-500','bg-yellow-500','bg-purple-500','bg-pink-500','bg-indigo-500','bg-red-500','bg-teal-500']; @endphp
                <div class="flex-shrink-0 flex gap-1">
                    @foreach($task->assignees->take(3) as $assignee)
                        @if($assignee->profile_image)
                            <img src="{{ route('profile.image.show', $assignee) }}"
                                 alt="{{ $assignee->name }}" title="{{ $assignee->name }}"
                                 class="w-6 h-6 rounded-full object-cover">
                        @else
                            <div class="w-6 h-6 rounded-full {{ $avatarColors[$assignee->id % count($avatarColors)] }} flex items-center justify-center text-xs font-bold text-white"
                                 title="{{ $assignee->name }}">
                                {{ strtoupper(substr($assignee->name, 0, 1)) }}
                            </div>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Metadata row --}}
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-1 text-xs text-gray-500">

            @if($task->time)
                <span>{{ \Carbon\Carbon::parse($task->time)->format('g:i A') }}</span>
            @endif

            @if($task->project)
                <span class="text-blue-400">{{ $task->project->name }}</span>
            @endif

            @if($showCreator && $task->creator && $task->creator_id !== auth()->id())
                <span class="text-gray-600">from <span class="text-gray-400">{{ $task->creator->name }}</span></span>
            @endif

            @if($subtitle)
                <span class="{{ str_starts_with($subtitle, '→') ? 'text-amber-500' : 'text-gray-500' }}">
                    {{ $subtitle }}
                </span>
            @endif

            @foreach($task->tags as $tag)
                <span class="px-1.5 py-0.5 rounded text-xs"
                      style="background-color:{{ $tag->color }}22; color:{{ $tag->color }}">
                    {{ $tag->tag_name }}
                </span>
            @endforeach

        </div>
    </div>
</div>
