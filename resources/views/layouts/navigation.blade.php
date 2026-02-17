<nav x-data="{ open: false }" class="bg-[#202020] border-b border-gray-700">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}">
                        <x-application-logo class="block h-9 w-auto fill-current text-gray-100" />
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('today')" :active="request()->routeIs('today') || request()->routeIs('dashboard')">
                        {{ __('Today') }}
                    </x-nav-link>
                    <x-nav-link :href="route('inbox')" :active="request()->routeIs('inbox')">
                        {{ __('Inbox') }}
                    </x-nav-link>
                    <x-nav-link :href="route('calendar')" :active="request()->routeIs('calendar') || request()->routeIs('overdue') || request()->routeIs('undated')">
                        {{ __('By Date') }}
                    </x-nav-link>
                    <x-nav-link :href="route('search')" :active="request()->routeIs('search')">
                        {{ __('Search') }}
                    </x-nav-link>
                    <x-nav-link :href="route('projects.index')" :active="request()->routeIs('projects.*')">
                        {{ __('Projects') }}
                    </x-nav-link>
                    <x-nav-link :href="route('tags.index')" :active="request()->routeIs('tags.*')">
                        {{ __('Tags') }}
                    </x-nav-link>
                    <x-nav-link :href="route('changelogs.user')" :active="request()->routeIs('changelogs.*')">
                        {{ __('Activity') }}
                    </x-nav-link>

                    @if($otherLinksFiles->isNotEmpty())
                        <!-- Other Links Dropdown -->
                        <div class="hidden sm:flex sm:items-center">
                            <x-dropdown align="left" width="48">
                                <x-slot name="trigger">
                                    <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-400 hover:text-gray-100 focus:outline-none transition ease-in-out duration-150">
                                        <div>{{ __('Other Links') }}</div>
                                        <div class="ms-1">
                                            <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                    </button>
                                </x-slot>
                                <x-slot name="content">
                                    @foreach($otherLinksFiles as $filename => $displayName)
                                        <x-dropdown-link href="/other-links/{{ $filename }}">
                                            {{ $displayName }}
                                        </x-dropdown-link>
                                    @endforeach
                                </x-slot>
                            </x-dropdown>
                        </div>
                    @endif

                    <x-nav-link :href="route('tasks.create')" :active="request()->routeIs('tasks.create')" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700">
                        Add Task
                    </x-nav-link>
                </div>
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden sm:flex sm:items-center sm:ms-6">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-300 bg-[#202020] hover:text-gray-100 focus:outline-none transition ease-in-out duration-150">
                            @if(Auth::user()->profile_image)
                                <img src="{{ route('profile.image.show', Auth::user()) }}"
                                     alt="{{ Auth::user()->name }}"
                                     class="w-8 h-8 rounded-full object-cover">
                            @else
                                @php
                                    $navAvatarColors = ['bg-blue-500', 'bg-green-500', 'bg-yellow-500', 'bg-purple-500', 'bg-pink-500', 'bg-indigo-500', 'bg-red-500', 'bg-teal-500'];
                                @endphp
                                <div class="w-8 h-8 rounded-full {{ $navAvatarColors[Auth::user()->id % count($navAvatarColors)] }} flex items-center justify-center text-sm font-bold text-white">
                                    {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                                </div>
                            @endif
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">
                            {{ __('Profile') }}
                        </x-dropdown-link>

                        <!-- Authentication -->
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault();
                                                this.closest('form').submit();">
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-100 hover:bg-gray-700 focus:outline-none focus:bg-gray-700 focus:text-gray-100 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('today')" :active="request()->routeIs('today') || request()->routeIs('dashboard')">
                {{ __('Today') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('inbox')" :active="request()->routeIs('inbox')">
                {{ __('Inbox') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('calendar')" :active="request()->routeIs('calendar') || request()->routeIs('overdue') || request()->routeIs('undated')">
                {{ __('By Date') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('search')" :active="request()->routeIs('search')">
                {{ __('Search') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('projects.index')" :active="request()->routeIs('projects.*')">
                {{ __('Projects') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('tags.index')" :active="request()->routeIs('tags.*')">
                {{ __('Tags') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('changelogs.user')" :active="request()->routeIs('changelogs.*')">
                {{ __('Activity') }}
            </x-responsive-nav-link>

            @if($otherLinksFiles->isNotEmpty())
                <!-- Other Links Collapsible Section -->
                <div x-data="{ otherLinksOpen: false }" class="border-t border-gray-700">
                    <button @click="otherLinksOpen = ! otherLinksOpen" class="w-full flex items-center justify-between px-4 py-2 text-base font-medium text-gray-400 hover:text-gray-100 hover:bg-gray-700 focus:outline-none focus:text-gray-100 focus:bg-gray-700 transition duration-150 ease-in-out">
                        <span>{{ __('Other Links') }}</span>
                        <svg class="h-5 w-5 transform transition-transform duration-200" :class="{'rotate-180': otherLinksOpen}" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                    <div x-show="otherLinksOpen" x-transition class="bg-[#101010]">
                        @foreach($otherLinksFiles as $filename => $displayName)
                            <x-responsive-nav-link href="/other-links/{{ $filename }}" :active="request()->routeIs('other.links.link') && request()->route('filename') === $filename">
                                {{ $displayName }}
                            </x-responsive-nav-link>
                        @endforeach
                    </div>
                </div>
            @endif

            <x-responsive-nav-link :href="route('tasks.create')" :active="request()->routeIs('tasks.create')" class="bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700">
                Add Task
            </x-responsive-nav-link>
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-gray-700">
            <div class="px-4 flex items-center gap-3">
                @if(Auth::user()->profile_image)
                    <img src="{{ route('profile.image.show', Auth::user()) }}"
                         alt="{{ Auth::user()->name }}"
                         class="w-10 h-10 rounded-full object-cover flex-shrink-0">
                @else
                    @php
                        $mobileAvatarColors = ['bg-blue-500', 'bg-green-500', 'bg-yellow-500', 'bg-purple-500', 'bg-pink-500', 'bg-indigo-500', 'bg-red-500', 'bg-teal-500'];
                    @endphp
                    <div class="w-10 h-10 rounded-full {{ $mobileAvatarColors[Auth::user()->id % count($mobileAvatarColors)] }} flex items-center justify-center text-sm font-bold text-white flex-shrink-0">
                        {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                    </div>
                @endif
                <div>
                    <div class="font-medium text-base text-gray-100">{{ Auth::user()->name }}</div>
                    <div class="font-medium text-sm text-gray-400">{{ Auth::user()->email }}</div>
                </div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')">
                    {{ __('Profile') }}
                </x-responsive-nav-link>

                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <x-responsive-nav-link :href="route('logout')"
                            onclick="event.preventDefault();
                                        this.closest('form').submit();">
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
