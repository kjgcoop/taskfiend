<section id="day-preferences" class="space-y-6">
    <header>
        <h2 class="text-lg font-medium text-gray-100">
            {{ __('Day Preferences') }}
        </h2>

        <p class="mt-1 text-sm text-gray-400">
            {{ __('Choose what appears on your day.') }}
        </p>
    </header>

    <form method="post" action="{{ route('profile.day-preferences.update') }}" class="space-y-4">
        @csrf
        @method('patch')

        <label class="flex items-start">
            <input type="checkbox" name="show_project_tasks_on_day" value="1"
                   {{ $user->show_project_tasks_on_day ? 'checked' : '' }}
                   class="mt-1 rounded border-gray-600 bg-gray-700 text-blue-600 focus:ring-blue-500">
            <span class="ms-2 text-sm text-gray-300">
                {{ __("Also show dated tasks from projects I'm assigned to") }}
                <span class="block text-gray-400">
                    {{ __("Tasks in your projects appear on that day even when they aren't assigned to you, so you can follow along. This applies to your day pages, exports and morning emails. Overdue, Undated and Calendar still show only tasks assigned to you.") }}
                </span>
            </span>
        </label>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

            @if (session('status') === 'day-preferences-updated')
                <p
                    x-data="flashMessage"
                    x-show="show"
                    x-transition
                    class="text-sm text-gray-400"
                >{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>
