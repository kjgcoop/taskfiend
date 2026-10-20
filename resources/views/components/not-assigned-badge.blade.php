@props(['task', 'compact' => false])

{{-- Marks a task that is on the viewer's day only because they belong to its project (profile: "Also show dated tasks from projects I'm assigned to"). Renders nothing for tasks on their own plate. Plain Blade: no Alpine directives. --}}
@unless($task->isOnPlateOf(Auth::id()))
    @php
        $assigneeNames = $task->assignees->pluck('name')->join(', ');
        $badgeTitle = 'Not assigned to you but in a project you follow' . ($assigneeNames !== '' ? ' (assigned to ' . $assigneeNames . ')' : '');
    @endphp
    <span data-not-assigned-badge title="{{ $badgeTitle }}"
          @class([
              'inline-flex items-center gap-1 text-gray-400',
              'text-xs border border-gray-600 rounded px-1.5 py-0.5 whitespace-nowrap' => !$compact,
          ])>
        <svg xmlns="http://www.w3.org/2000/svg" class="{{ $compact ? 'inline h-3 w-3 opacity-70' : 'h-3 w-3' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
        </svg>
        @unless($compact)
            <span>Not Assigned to You</span>
        @else
            <span class="sr-only">Not Assigned to You</span>
        @endunless
    </span>
@endunless
