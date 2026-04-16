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
                    <x-nav-link :href="route('day')" :active="request()->routeIs('today') || request()->routeIs('dashboard') || (request()->routeIs('day') && !request()->has('date'))">
                        {{ __('Today') }}
                    </x-nav-link>
                    <!-- <x-nav-link :href="route('inbox')" :active="request()->routeIs('inbox')">
                        {{ __('Inbox') }}
                    </x-nav-link>-->
                    <div class="relative flex items-center" x-data="{ open: false }" @click.outside="open = false" @close.stop="open = false">
                        @php
                            $byDateActive = request()->routeIs('calendar') || request()->routeIs('overdue') || request()->routeIs('undated');
                        @endphp
                        <a href="{{ route('calendar') }}" class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5 focus:outline-none transition duration-150 ease-in-out {{ $byDateActive ? 'border-indigo-400 text-gray-100' : 'border-transparent text-gray-400 hover:text-gray-100 hover:border-gray-500' }}">
                            By Date
                        </a>
                        <button @click="open = !open" class="inline-flex items-center pl-0.5 text-gray-400 hover:text-gray-100 focus:outline-none transition duration-150 ease-in-out">
                            <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                        <div x-show="open"
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-75"
                             x-transition:leave-start="opacity-100 scale-100"
                             x-transition:leave-end="opacity-0 scale-95"
                             class="absolute z-50 top-full mt-2 w-48 rounded-md shadow-lg ltr:origin-top-left start-0"
                             style="display: none;"
                             @click="open = false">
                            <div class="rounded-md ring-1 ring-black ring-opacity-5 py-1 bg-[#202020]">
                                <a href="{{ route('calendar') }}" class="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-300 hover:bg-gray-700 focus:outline-none transition duration-150 ease-in-out {{ request()->routeIs('calendar') ? 'bg-gray-700 text-gray-100' : '' }}">
                                    {{ __('Calendar') }}
                                </a>
                                <a href="{{ route('overdue') }}" class="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-300 hover:bg-gray-700 focus:outline-none transition duration-150 ease-in-out {{ request()->routeIs('overdue') ? 'bg-gray-700 text-gray-100' : '' }}">
                                    {{ __('Overdue') }}
                                </a>
                                <a href="{{ route('undated') }}" class="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-300 hover:bg-gray-700 focus:outline-none transition duration-150 ease-in-out {{ request()->routeIs('undated') ? 'bg-gray-700 text-gray-100' : '' }}">
                                    {{ __('Undated') }}
                                </a>
                            </div>
                        </div>
                    </div>
                    <x-nav-link :href="route('search')" :active="request()->routeIs('search')">
                        {{ __('Search') }}
                    </x-nav-link>
                    <div class="relative flex items-center" x-data="{ open: false }" @click.outside="open = false" @close.stop="open = false">
                        <a href="{{ route('projects.index') }}" class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5 focus:outline-none transition duration-150 ease-in-out {{ request()->routeIs('projects.*') ? 'border-indigo-400 text-gray-100' : 'border-transparent text-gray-400 hover:text-gray-100 hover:border-gray-500' }}">
                            {{ __('Projects') }}
                        </a>
                        <button @click="open = !open" class="inline-flex items-center pl-0.5 text-gray-400 hover:text-gray-100 focus:outline-none transition duration-150 ease-in-out">
                            <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                        <div x-show="open"
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-75"
                             x-transition:leave-start="opacity-100 scale-100"
                             x-transition:leave-end="opacity-0 scale-95"
                             class="absolute z-50 top-full mt-2 w-56 rounded-md shadow-lg ltr:origin-top-left start-0"
                             style="display: none;"
                             @click="open = false">
                            <div class="rounded-md ring-1 ring-black ring-opacity-5 py-1 bg-[#202020] max-h-64 overflow-y-auto">
                                @forelse($navProjects as $project)
                                    @php $hasBg = !empty($project->background_image); @endphp
                                    <a href="{{ route('projects.show', $project) }}"
                                       class="relative block w-full text-start text-sm leading-5 focus:outline-none transition duration-150 ease-in-out overflow-hidden {{ $hasBg ? 'border-b-2 border-[#202020]' : 'px-4 py-2 text-gray-300 hover:bg-gray-700' }}"
                                       @if($hasBg)
                                       style="background-image: url('{{ route('projects.background', $project) }}'); background-size: cover; background-position: center;"
                                       @endif>
                                        @if($hasBg)
                                            <div class="absolute inset-0 bg-black/60 hover:bg-black/45 transition duration-150 ease-in-out"></div>
                                        @endif
                                        <span class="relative px-4 py-2 block {{ $hasBg ? 'text-white' : '' }}">{{ $project->name }}</span>
                                    </a>
                                @empty
                                    <span class="block px-4 py-2 text-sm text-gray-500 italic">No active projects</span>
                                @endforelse
                            </div>
                        </div>
                    </div>
                    <div class="relative flex items-center" x-data="{ open: false }" @click.outside="open = false" @close.stop="open = false">
                        <a href="{{ route('tags.index') }}" class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5 focus:outline-none transition duration-150 ease-in-out {{ request()->routeIs('tags.*') ? 'border-indigo-400 text-gray-100' : 'border-transparent text-gray-400 hover:text-gray-100 hover:border-gray-500' }}">
                            {{ __('Tags') }}
                        </a>
                        <button @click="open = !open" class="inline-flex items-center pl-0.5 text-gray-400 hover:text-gray-100 focus:outline-none transition duration-150 ease-in-out">
                            <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                        <div x-show="open"
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-75"
                             x-transition:leave-start="opacity-100 scale-100"
                             x-transition:leave-end="opacity-0 scale-95"
                             class="absolute z-50 top-full mt-2 w-48 rounded-md shadow-lg ltr:origin-top-left start-0"
                             style="display: none;"
                             @click="open = false">
                            <div class="rounded-md ring-1 ring-black ring-opacity-5 bg-[#202020] max-h-64 overflow-y-auto">
                                @forelse($navTags as $tag)
                                    <a href="{{ route('tags.show', $tag) }}"
                                       class="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-300 hover:bg-gray-700 border-l-4 focus:outline-none transition duration-150 ease-in-out"
                                       style="border-left-color: {{ $tag->color ?? 'transparent' }}">
                                        {{ $tag->tag_name }}
                                    </a>
                                @empty
                                    <span class="block px-4 py-2 text-sm text-gray-500 italic">No tags yet</span>
                                @endforelse
                                <a href="{{ route('tags.create') }}"
                                   class="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-300 hover:bg-gray-700 border-l-4 focus:outline-none transition duration-150 ease-in-out"
                                   style="border-left-color: transparent">
                                    Add New Tag
                                </a>
                            </div>
                        </div>
                    </div>
                    <!-- More Dropdown (Templates + Activity + Other Links) -->
                    <div class="relative flex items-center" x-data="{ open: false }" @click.outside="open = false" @close.stop="open = false">
                        <button @click="open = !open" class="inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-gray-400 hover:text-gray-100 hover:border-gray-500 focus:outline-none transition duration-150 ease-in-out {{ request()->routeIs('templates.*') || request()->routeIs('changelogs.*') || request()->routeIs('other.links.*') ? 'border-indigo-400 text-gray-100' : '' }}">
                            {{ __('More') }}
                            <svg class="ms-1 fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                        <div x-show="open"
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-75"
                             x-transition:leave-start="opacity-100 scale-100"
                             x-transition:leave-end="opacity-0 scale-95"
                             class="absolute z-50 top-full mt-2 w-48 rounded-md shadow-lg ltr:origin-top-left start-0"
                             style="display: none;"
                             @click="open = false">
                            <div class="rounded-md ring-1 ring-black ring-opacity-5 py-1 bg-[#202020]">
                                <a href="{{ route('templates.index') }}" class="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-300 hover:bg-gray-700 focus:outline-none transition duration-150 ease-in-out {{ request()->routeIs('templates.*') ? 'bg-gray-700 text-gray-100' : '' }}">
                                    {{ __('Templates') }}
                                </a>
                                <a href="{{ route('changelogs.user') }}" class="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-300 hover:bg-gray-700 focus:outline-none transition duration-150 ease-in-out {{ request()->routeIs('changelogs.*') ? 'bg-gray-700 text-gray-100' : '' }}">
                                    {{ __('Activity') }}
                                </a>
                                @if($otherLinksFiles->isNotEmpty())
                                    <div class="border-t border-gray-700 my-1"></div>
                                    @foreach($otherLinksFiles as $filename => $displayName)
                                        <a href="/other-links/{{ $filename }}" class="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-300 hover:bg-gray-700 focus:outline-none transition duration-150 ease-in-out {{ request()->routeIs('other.links.link') && request()->route('path') === $filename ? 'bg-gray-700 text-gray-100' : '' }}">
                                            {{ $displayName }}
                                        </a>
                                    @endforeach
                                @endif
                            </div>
                        </div>
                    </div>

                    <x-nav-link :href="route('tasks.create')" :active="request()->routeIs('tasks.create')" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700">
                        +
                    </x-nav-link>
                </div>
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden sm:flex sm:items-center sm:ms-6">
                @auth
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button data-testid="user-menu" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-300 bg-[#202020] hover:text-gray-100 focus:outline-none transition ease-in-out duration-150">
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
                @endauth
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
            <x-responsive-nav-link :href="route('day')" :active="request()->routeIs('today') || request()->routeIs('dashboard') || (request()->routeIs('day') && !request()->has('date'))">
                {{ __('Today') }}
            </x-responsive-nav-link>
            <!-- <x-responsive-nav-link :href="route('inbox')" :active="request()->routeIs('inbox')">
                {{ __('Inbox') }}
            </x-responsive-nav-link> -->
            <div x-data="{ byDateOpen: false }">
                <div class="flex">
                    <a href="{{ route('calendar') }}" class="flex-1 flex items-center ps-3 pe-4 py-2 border-l-4 text-base font-medium transition duration-150 ease-in-out focus:outline-none {{ request()->routeIs('calendar') || request()->routeIs('overdue') || request()->routeIs('undated') ? 'border-indigo-400 text-indigo-300 bg-gray-700' : 'border-transparent text-gray-400 hover:text-gray-100 hover:bg-gray-700 hover:border-gray-500' }}">
                        {{ __('By Date') }}
                    </a>
                    <button @click="byDateOpen = !byDateOpen" class="px-4 py-2 text-gray-400 hover:text-gray-100 hover:bg-gray-700 focus:outline-none transition duration-150 ease-in-out">
                        <svg class="h-5 w-5 transform transition-transform duration-200" :class="{'rotate-180': byDateOpen}" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>
                <div x-show="byDateOpen" x-transition class="bg-[#101010]">
                    <x-responsive-nav-link :href="route('calendar')" :active="request()->routeIs('calendar')">
                        {{ __('Calendar') }}
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('overdue')" :active="request()->routeIs('overdue')">
                        {{ __('Overdue') }}
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('undated')" :active="request()->routeIs('undated')">
                        {{ __('Undated') }}
                    </x-responsive-nav-link>
                </div>
            </div>
            <x-responsive-nav-link :href="route('search')" :active="request()->routeIs('search')">
                {{ __('Search') }}
            </x-responsive-nav-link>
            <div x-data="{ projectsOpen: false }">
                <div class="flex">
                    <a href="{{ route('projects.index') }}" class="flex-1 flex items-center ps-3 pe-4 py-2 border-l-4 text-base font-medium transition duration-150 ease-in-out focus:outline-none {{ request()->routeIs('projects.*') ? 'border-indigo-400 text-indigo-300 bg-gray-700' : 'border-transparent text-gray-400 hover:text-gray-100 hover:bg-gray-700 hover:border-gray-500' }}">
                        {{ __('Projects') }}
                    </a>
                    <button @click="projectsOpen = !projectsOpen" class="px-4 py-2 text-gray-400 hover:text-gray-100 hover:bg-gray-700 focus:outline-none transition duration-150 ease-in-out">
                        <svg class="h-5 w-5 transform transition-transform duration-200" :class="{'rotate-180': projectsOpen}" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>
                <div x-show="projectsOpen" x-transition class="bg-[#101010]">
                    @forelse($navProjects as $project)
                        @php $hasBg = !empty($project->background_image); @endphp
                        <a href="{{ route('projects.show', $project) }}"
                           class="relative block w-full ps-3 pe-4 py-2 border-l-4 border-transparent text-base font-medium focus:outline-none transition duration-150 ease-in-out overflow-hidden {{ $hasBg ? 'border-b-2 border-[#202020]' : 'text-gray-400 hover:text-gray-100 hover:bg-gray-700 hover:border-gray-500' }}"
                           @if($hasBg)
                           style="background-image: url('{{ route('projects.background', $project) }}'); background-size: cover; background-position: center;"
                           @endif>
                            @if($hasBg)
                                <div class="absolute inset-0 bg-black/60 hover:bg-black/45 transition duration-150 ease-in-out"></div>
                            @endif
                            <span class="relative {{ $hasBg ? 'text-white' : '' }}">{{ $project->name }}</span>
                        </a>
                    @empty
                        <span class="block ps-6 pe-4 py-2 text-sm text-gray-500 italic">No active projects</span>
                    @endforelse
                </div>
            </div>
            <div x-data="{ tagsOpen: false }">
                <div class="flex">
                    <a href="{{ route('tags.index') }}" class="flex-1 flex items-center ps-3 pe-4 py-2 border-l-4 text-base font-medium transition duration-150 ease-in-out focus:outline-none {{ request()->routeIs('tags.*') ? 'border-indigo-400 text-indigo-300 bg-gray-700' : 'border-transparent text-gray-400 hover:text-gray-100 hover:bg-gray-700 hover:border-gray-500' }}">
                        {{ __('Tags') }}
                    </a>
                    <button @click="tagsOpen = !tagsOpen" class="px-4 py-2 text-gray-400 hover:text-gray-100 hover:bg-gray-700 focus:outline-none transition duration-150 ease-in-out">
                        <svg class="h-5 w-5 transform transition-transform duration-200" :class="{'rotate-180': tagsOpen}" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>
                <div x-show="tagsOpen" x-transition class="bg-[#101010]">
                    @forelse($navTags as $tag)
                        <a href="{{ route('tags.show', $tag) }}"
                           class="block w-full ps-3 pe-4 py-2 border-l-4 text-base font-medium text-gray-400 hover:text-gray-100 hover:bg-gray-700 focus:outline-none transition duration-150 ease-in-out"
                           style="border-left-color: {{ $tag->color ?? 'transparent' }}">
                            {{ $tag->tag_name }}
                        </a>
                    @empty
                        <span class="block ps-6 pe-4 py-2 text-sm text-gray-500 italic">No tags yet</span>
                    @endforelse
                </div>
            </div>
            <x-responsive-nav-link :href="route('templates.index')" :active="request()->routeIs('templates.*')">
                {{ __('Templates') }}
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
                            <x-responsive-nav-link href="/other-links/{{ $filename }}" :active="request()->routeIs('other.links.link') && request()->route('path') === $filename">
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
        @auth
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
        @endauth
    </div>
</nav>
