<section>
    <header>
        <h2 class="text-lg font-medium text-gray-100">
            {{ __('Profile Information') }}
        </h2>

        <p class="mt-1 text-sm text-gray-400">
            {{ __("Update your account's profile information and email address.") }}
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <!-- Profile Image -->
    <div class="mt-6 flex items-center gap-6">
        <div class="flex-shrink-0">
            @if($user->profile_image)
                <img src="{{ route('profile.image.show', $user) }}"
                     alt="Profile photo"
                     class="w-20 h-20 rounded-full object-cover border-2 border-gray-600">
            @else
                @php
                    $avatarColors = ['bg-blue-500', 'bg-green-500', 'bg-yellow-500', 'bg-purple-500', 'bg-pink-500', 'bg-indigo-500', 'bg-red-500', 'bg-teal-500'];
                    $colorClass = $avatarColors[$user->id % count($avatarColors)];
                @endphp
                <div class="w-20 h-20 rounded-full {{ $colorClass }} flex items-center justify-center text-2xl font-bold text-white">
                    {{ strtoupper(substr($user->name, 0, 1)) }}
                </div>
            @endif
        </div>
        <div class="space-y-2">
            <form method="post" action="{{ route('profile.image.update') }}" enctype="multipart/form-data" class="flex items-center gap-3">
                @csrf
                <label class="cursor-pointer px-4 py-2 bg-gray-700 border border-gray-600 rounded-md text-sm text-gray-300 hover:bg-gray-600 transition">
                    {{ __('Choose Photo') }}
                    <input type="file" name="profile_image" accept="image/*" class="hidden"
                           onchange="this.closest('form').submit()">
                </label>
            </form>
            @if($user->profile_image)
                <form method="post" action="{{ route('profile.image.destroy') }}">
                    @csrf
                    @method('delete')
                    <button type="submit" class="text-sm text-red-400 hover:text-red-300 transition">
                        {{ __('Remove photo') }}
                    </button>
                </form>
            @endif
            <x-input-error class="mt-1" :messages="$errors->get('profile_image')" />
        </div>
    </div>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        <div>
            <x-input-label for="name" :value="__('Name')" class="!text-gray-300" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full bg-gray-700 border-gray-600 text-gray-100 placeholder-gray-500" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" class="!text-gray-300" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full bg-gray-700 border-gray-600 text-gray-100 placeholder-gray-500" :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div>
                    <p class="text-sm mt-2 text-gray-300">
                        {{ __('Your email address is unverified.') }}

                        <button form="send-verification" class="underline text-sm text-gray-400 hover:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-sm text-green-600">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-gray-400"
                >{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>
