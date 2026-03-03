<section class="space-y-6">
    <header>
        <h2 class="text-lg font-medium text-gray-100">
            {{ __('Sign Out All Sessions') }}
        </h2>

        <p class="mt-1 text-sm text-gray-400">
            {{ __('Invalidate all active sessions for your account, including this one. You will be signed out and redirected to the login page.') }}
        </p>
    </header>

    <form method="post" action="{{ route('profile.sessions.destroy') }}">
        @csrf
        @method('delete')

        <x-danger-button>
            {{ __('Sign Out All Sessions') }}
        </x-danger-button>
    </form>
</section>
