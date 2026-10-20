@props(['task'])

{{-- Longer form of <x-not-assigned-badge>, for the task panel: explains why a task the viewer isn't assigned to is in front of them, and where the setting lives. Plain Blade: no Alpine directives. Renders nothing for tasks on the viewer's plate. --}}
@unless($task->isOnPlateOf(Auth::id()))
    <div data-not-assigned-notice class="p-3 bg-blue-900 bg-opacity-20 border border-blue-500 rounded-lg">
        <p class="text-sm text-blue-300">
            <span class="font-semibold">Not assigned to you.</span>
            This task is not assigned to you directly, but you can see it because it's in a project you're assigned to.
            @if(Auth::user()->show_project_tasks_on_day)
                It's shown on your day because of your Day Preferences. To hide tasks that aren't assigned to you, change that setting in your
            @else
                To show tasks like this on your day, change the setting in your
            @endif
            <a href="{{ route('profile.edit') }}#day-preferences" class="underline hover:text-blue-200">profile</a>.
        </p>
    </div>
@endunless
