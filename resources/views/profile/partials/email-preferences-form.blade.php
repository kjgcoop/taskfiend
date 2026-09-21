<section class="space-y-6">
    <header>
        <h2 class="text-lg font-medium text-gray-100">
            {{ __('Email Preferences') }}
        </h2>

        <p class="mt-1 text-sm text-gray-400">
            {{ __("Choose which emails you'd like to receive. Everything is opted out by default.") }}
        </p>
    </header>

    <form method="post" action="{{ route('profile.email-preferences.update') }}" class="space-y-4">
        @csrf
        @method('patch')

        @foreach (\App\Models\EmailSubscription::TYPES as $type => $label)
            <label class="flex items-center">
                <input type="checkbox" name="subscriptions[]" value="{{ $type }}"
                       {{ $user->isSubscribedToEmail($type) ? 'checked' : '' }}
                       class="rounded border-gray-600 bg-gray-700 text-blue-600 focus:ring-blue-500">
                <span class="ms-2 text-sm text-gray-300">{{ $label }}</span>
            </label>
        @endforeach

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

            @if (session('status') === 'email-preferences-updated')
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
