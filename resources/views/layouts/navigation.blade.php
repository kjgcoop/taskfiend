<nav x-data="dropdown" class="bg-[#202020] border-b border-gray-700">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 md:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}">
                        <x-application-logo class="block h-9 w-auto fill-current text-gray-100" />
                    </a>
                </div>

                <!-- Navigation Links -->
                {{-- Hamburger layout below md; tighter spacing from md to lg so the full header fits an unfolded foldable. --}}
                <div class="hidden space-x-4 lg:space-x-8 md:-my-px md:ms-4 lg:ms-10 md:flex">
                    <x-nav-link :href="route('day')" :active="request()->routeIs('today') || request()->routeIs('dashboard') || (request()->routeIs('day') && !request()->has('date'))">
                        {{ __('Today') }}
                    </x-nav-link>
                    <!-- <x-nav-link :href="route('inbox')" :active="request()->routeIs('inbox')">
                        {{ __('Inbox') }}
                    </x-nav-link>-->
                    <div class="relative flex items-center" x-data="dropdown" @click.outside="open = false" @close.stop="open = false">
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
                    <x-nav-link :href="route('search')" :active="request()->routeIs('search')" class="!hidden lg:!inline-flex">
                        {{ __('Search') }}
                    </x-nav-link>
                    <div class="relative flex items-center" x-data="dropdown" @click.outside="open = false" @close.stop="open = false">
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
                                       class="relative block w-full text-start text-sm leading-5 focus:outline-none transition duration-150 ease-in-out overflow-hidden {{ $hasBg ? 'border-b-2 border-[#202020]' : 'py-2 text-gray-300 hover:bg-gray-700' }}"
                                       @if($hasBg)
                                       style="background-image: url('{{ route('projects.background', $project) }}'); background-size: cover; background-position: center;"
                                       @endif>
                                        @if($hasBg)
                                            <div class="absolute inset-0 bg-black/60 hover:bg-black/45 transition duration-150 ease-in-out"></div>
                                        @endif
                                        <span class="relative px-4 py-2 flex items-center gap-1.5 {{ $hasBg ? 'text-white' : '' }}">
                                            @if($project->is_hearted)
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 shrink-0 {{ $hasBg ? 'text-pink-300' : 'text-pink-500' }}" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd" />
                                                </svg>
                                            @endif
                                            {{ $project->name }}
                                        </span>
                                    </a>
                                @empty
                                    <span class="block px-4 py-2 text-sm text-gray-500 italic">No active projects</span>
                                @endforelse
                                <a href="{{ route('projects.create') }}" class="relative block w-full text-start text-sm leading-5 focus:outline-none transition duration-150 ease-in-out overflow-hidden py-2 text-gray-300 hover:bg-gray-700"
                                    style="border-left-color: transparent">
                                    <span class="relative px-4 py-2 block">Add New Project</span>
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="relative flex items-center" x-data="dropdown" @click.outside="open = false" @close.stop="open = false">
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
                    <div class="relative flex items-center" x-data="dropdown" @click.outside="open = false" @close.stop="open = false">
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
                                {{-- Search's own nav link is hidden below lg to save width; offer it here instead. --}}
                                <a href="{{ route('search') }}" class="lg:hidden block w-full px-4 py-2 text-start text-sm leading-5 text-gray-300 hover:bg-gray-700 focus:outline-none transition duration-150 ease-in-out {{ request()->routeIs('search') ? 'bg-gray-700 text-gray-100' : '' }}">
                                    {{ __('Search') }}
                                </a>
                                <a href="https://taskfiend.online" class="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-300 hover:bg-gray-700 focus:outline-none transition duration-150 ease-in-out">
                                    Documentation
                                </a>
                                <!-- Templates, with an inline expandable list of templates -->
                                <div x-data="dropdown">
                                    <div class="flex">
                                        <a href="{{ route('templates.index') }}" class="flex-1 px-4 py-2 text-start text-sm leading-5 text-gray-300 hover:bg-gray-700 focus:outline-none transition duration-150 ease-in-out {{ request()->routeIs('templates.*') ? 'bg-gray-700 text-gray-100' : '' }}">
                                            {{ __('Templates') }}
                                        </a>
                                        {{-- .stop: the More panel closes itself on any click inside it --}}
                                        <button @click.stop="open = !open" class="px-3 text-gray-400 hover:text-gray-100 hover:bg-gray-700 focus:outline-none transition duration-150 ease-in-out" title="Show templates">
                                            <svg class="h-4 w-4 fill-current transform transition-transform duration-200" :class="{'rotate-180': open}" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                            </svg>
                                        </button>
                                    </div>
                                    <div x-show="open" class="bg-[#101010] max-h-64 overflow-y-auto" style="display: none;">
                                        @forelse($navTemplates as $template)
                                            <a href="{{ route('templates.index') }}#template-{{ $template->id }}" class="block w-full ps-7 pe-4 py-2 text-start text-sm leading-5 text-gray-300 hover:bg-gray-700 focus:outline-none transition duration-150 ease-in-out">
                                                {{ $template->name }}
                                            </a>
                                        @empty
                                            <span class="block ps-7 pe-4 py-2 text-sm text-gray-500 italic">No templates</span>
                                        @endforelse
                                    </div>
                                </div>
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

                </div>
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden md:flex md:items-center md:gap-1 lg:gap-3 md:ms-3 lg:ms-6">
                @auth
                <!-- Quick Search -->
                <div class="relative flex items-center" x-data="navSearch" @click.outside="close()">
                    <div class="flex items-center gap-2">
                        <button @click="toggle()"
                                class="inline-flex items-center justify-center w-9 h-9 text-gray-400 hover:text-gray-100 rounded-md hover:bg-gray-700 transition-colors"
                                title="Search">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z" />
                            </svg>
                        </button>
                        <div x-show="open"
                             x-transition:enter="transition ease-out duration-150"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-100"
                             x-transition:leave-start="opacity-100 scale-100"
                             x-transition:leave-end="opacity-0 scale-95"
                             style="display:none;">
                            <div class="relative">
                                <input x-ref="searchInput"
                                       x-model="query"
                                       type="text"
                                       placeholder="Search…"
                                       class="w-40 lg:w-56 bg-gray-700 text-gray-100 placeholder-gray-400 text-sm rounded-md px-3 py-1.5 border border-gray-600 focus:outline-none focus:border-indigo-500"
                                       :class="query ? 'pr-7' : ''"
                                       @keydown.enter="submit()"
                                       @keydown.escape="close()">
                                <button x-show="query"
                                        @click="clearQuery()"
                                        type="button"
                                        class="absolute inset-y-0 right-0 flex items-center pr-2 text-gray-400 hover:text-gray-100"
                                        tabindex="-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <a href="{{ route('tasks.create') }}" title="New Task"
                   class="inline-flex items-center justify-center w-9 h-9 bg-blue-600 hover:bg-blue-700 text-white rounded-md transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                </a>

                <!-- Notifications Bell -->
                <div class="relative" x-data="notificationsMenu" @click.outside="open = false">
                    <button @click="toggle()"
                            class="relative inline-flex items-center justify-center w-9 h-9 text-gray-400 hover:text-gray-100 rounded-md hover:bg-gray-700 transition-colors"
                            title="Notifications">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 10-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                        {{-- Always rendered (hidden at 0) so the heartbeat in layouts/app.blade.php can show it
                             when a notification arrives after page load. --}}
                        <span data-notif-badge="count" @class([
                                'absolute -top-1 -right-1 items-center justify-center w-4 h-4 text-xs font-bold text-white bg-red-500 rounded-full',
                                'inline-flex' => $unreadNotifications > 0,
                                'hidden' => $unreadNotifications <= 0,
                              ])>{{ $unreadNotifications > 9 ? '9+' : $unreadNotifications }}</span>
                    </button>

                    <div x-show="open"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-75"
                         x-transition:leave-start="opacity-100 scale-100"
                         x-transition:leave-end="opacity-0 scale-95"
                         class="absolute right-0 top-full mt-2 w-80 rounded-md shadow-lg bg-gray-800 border border-gray-700 z-50 overflow-hidden"
                         style="display: none;">
                        <div class="px-4 py-2 border-b border-gray-700 flex items-center justify-between">
                            <span class="text-sm font-semibold text-gray-200">Activity</span>
                            <a href="{{ route('changelogs.user') }}" class="text-xs text-blue-400 hover:underline">View all</a>
                        </div>
                        <div class="max-h-96 overflow-y-auto">
                            <template x-if="!loaded">
                                <div class="px-4 py-6 text-center text-sm text-gray-500">Loading…</div>
                            </template>
                            <template x-if="loaded">
                                <div x-ref="notifHtml"></div>
                            </template>
                        </div>
                    </div>
                </div>
                @endauth
                @auth
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button data-testid="user-menu" class="inline-flex items-center px-1 lg:px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-300 bg-[#202020] hover:text-gray-100 focus:outline-none transition ease-in-out duration-150">
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
                        <form method="POST" action="{{ route('logout') }}" id="logout-form-desktop">
                            @csrf
                            <button type="button" data-testid="logout-btn" @click="$el.closest('form').requestSubmit()" class="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-300 hover:bg-gray-700 focus:outline-none focus:bg-gray-700 transition duration-150 ease-in-out">
                                {{ __('Log Out') }}
                            </button>
                        </form>
                    </x-slot>
                </x-dropdown>
                @endauth
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center gap-1 md:hidden">
                @auth
                <div x-data="navSearch" @click.outside="close()">
                    <div class="flex items-center gap-1">
                        <button @click="toggle()"
                                class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-100 hover:bg-gray-700 focus:outline-none transition duration-150 ease-in-out"
                                title="Search">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z" />
                            </svg>
                        </button>
                        <div x-show="open"
                             x-transition:enter="transition ease-out duration-150"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-100"
                             x-transition:leave-start="opacity-100 scale-100"
                             x-transition:leave-end="opacity-0 scale-95"
                             style="display:none;">
                            <div class="relative">
                                <input x-ref="searchInput"
                                       x-model="query"
                                       type="text"
                                       placeholder="Search…"
                                       class="w-40 bg-gray-700 text-gray-100 placeholder-gray-400 text-sm rounded-md px-3 py-1.5 border border-gray-600 focus:outline-none focus:border-indigo-500"
                                       :class="query ? 'pr-7' : ''"
                                       @keydown.enter="submit()"
                                       @keydown.escape="close()">
                                <button x-show="query"
                                        @click="clearQuery()"
                                        type="button"
                                        class="absolute inset-y-0 right-0 flex items-center pr-2 text-gray-400 hover:text-gray-100"
                                        tabindex="-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                @endauth
                <button @click="open = ! open" class="relative inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-100 hover:bg-gray-700 focus:outline-none focus:bg-gray-700 focus:text-gray-100 transition duration-150 ease-in-out">
                    @auth
                    {{-- Unread-notifications dot; the count itself is on the Notifications item inside the menu. --}}
                    <span data-notif-badge="dot" @class([
                            'absolute top-1.5 right-1.5 w-2.5 h-2.5 bg-red-500 rounded-full ring-2 ring-[#202020]',
                            'hidden' => $unreadNotifications <= 0,
                          ])></span>
                    @endauth
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden md:hidden">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('day')" :active="request()->routeIs('today') || request()->routeIs('dashboard') || (request()->routeIs('day') && !request()->has('date'))">
                {{ __('Today') }}
            </x-responsive-nav-link>
            <!-- <x-responsive-nav-link :href="route('inbox')" :active="request()->routeIs('inbox')">
                {{ __('Inbox') }}
            </x-responsive-nav-link> -->
            <div x-data="dropdown">
                <div class="flex">
                    <a href="{{ route('calendar') }}" class="flex-1 flex items-center ps-3 pe-4 py-2 border-l-4 text-base font-medium transition duration-150 ease-in-out focus:outline-none {{ request()->routeIs('calendar') || request()->routeIs('overdue') || request()->routeIs('undated') ? 'border-indigo-400 text-indigo-300 bg-gray-700' : 'border-transparent text-gray-400 hover:text-gray-100 hover:bg-gray-700 hover:border-gray-500' }}">
                        {{ __('By Date') }}
                    </a>
                    <button @click="open = !open" class="px-4 py-2 text-gray-400 hover:text-gray-100 hover:bg-gray-700 focus:outline-none transition duration-150 ease-in-out">
                        <svg class="h-5 w-5 transform transition-transform duration-200" :class="{'rotate-180': open}" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>
                <div x-show="open" x-transition class="bg-[#101010]">
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
            <div x-data="dropdown">
                <div class="flex">
                    <a href="{{ route('projects.index') }}" class="flex-1 flex items-center ps-3 pe-4 py-2 border-l-4 text-base font-medium transition duration-150 ease-in-out focus:outline-none {{ request()->routeIs('projects.*') ? 'border-indigo-400 text-indigo-300 bg-gray-700' : 'border-transparent text-gray-400 hover:text-gray-100 hover:bg-gray-700 hover:border-gray-500' }}">
                        {{ __('Projects') }}
                    </a>
                    <button @click="open = !open" class="px-4 py-2 text-gray-400 hover:text-gray-100 hover:bg-gray-700 focus:outline-none transition duration-150 ease-in-out">
                        <svg class="h-5 w-5 transform transition-transform duration-200" :class="{'rotate-180': open}" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>
                <div x-show="open" x-transition class="bg-[#101010]">
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
                            <span class="relative flex items-center gap-1.5 {{ $hasBg ? 'text-white' : '' }}">
                                @if($project->is_hearted)
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 shrink-0 {{ $hasBg ? 'text-pink-300' : 'text-pink-500' }}" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd" />
                                    </svg>
                                @endif
                                {{ $project->name }}
                            </span>
                        </a>
                    @empty
                        <span class="block ps-6 pe-4 py-2 text-sm text-gray-500 italic">No active projects</span>
                    @endforelse
                    <a href="{{ route('projects.create') }}"
                       class="relative block w-full ps-3 pe-4 py-2 border-l-4 border-transparent text-base font-medium focus:outline-none transition duration-150 ease-in-out overflow-hidden text-gray-400 hover:text-gray-100 hover:bg-gray-700 hover:border-gray-500"
                    >
                        <span class="relative flex items-center gap-1.5">
                            Add New Project
                        </span>
                    </a>
                </div>
            </div>
            <div x-data="dropdown">
                <div class="flex">
                    <a href="{{ route('tags.index') }}" class="flex-1 flex items-center ps-3 pe-4 py-2 border-l-4 text-base font-medium transition duration-150 ease-in-out focus:outline-none {{ request()->routeIs('tags.*') ? 'border-indigo-400 text-indigo-300 bg-gray-700' : 'border-transparent text-gray-400 hover:text-gray-100 hover:bg-gray-700 hover:border-gray-500' }}">
                        {{ __('Tags') }}
                    </a>
                    <button @click="open = !open" class="px-4 py-2 text-gray-400 hover:text-gray-100 hover:bg-gray-700 focus:outline-none transition duration-150 ease-in-out">
                        <svg class="h-5 w-5 transform transition-transform duration-200" :class="{'rotate-180': open}" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>
                <div x-show="open" x-transition class="bg-[#101010]">
                    @forelse($navTags as $tag)
                        <a href="{{ route('tags.show', $tag) }}"
                           class="block w-full ps-3 pe-4 py-2 border-l-4 text-base font-medium text-gray-400 hover:text-gray-100 hover:bg-gray-700 focus:outline-none transition duration-150 ease-in-out"
                           style="border-left-color: {{ $tag->color ?? 'transparent' }}">
                            {{ $tag->tag_name }}
                        </a>
                    @empty
                        <span class="block ps-6 pe-4 py-2 text-sm text-gray-500 italic">No tags yet</span>
                    @endforelse
                    <a href="{{ route('tags.create') }}"
                       class="block w-full ps-3 pe-4 py-2 border-l-4 text-base font-medium text-gray-400 hover:text-gray-100 hover:bg-gray-700 focus:outline-none transition duration-150 ease-in-out"
                       style="border-left-color: transparent">
                        Add New Tag
                    </a>

                </div>
            </div>
            <div x-data="dropdown">
                <div class="flex">
                    <a href="{{ route('templates.index') }}" class="flex-1 flex items-center ps-3 pe-4 py-2 border-l-4 text-base font-medium transition duration-150 ease-in-out focus:outline-none {{ request()->routeIs('templates.*') ? 'border-indigo-400 text-indigo-300 bg-gray-700' : 'border-transparent text-gray-400 hover:text-gray-100 hover:bg-gray-700 hover:border-gray-500' }}">
                        {{ __('Templates') }}
                    </a>
                    <button @click="open = !open" class="px-4 py-2 text-gray-400 hover:text-gray-100 hover:bg-gray-700 focus:outline-none transition duration-150 ease-in-out">
                        <svg class="h-5 w-5 transform transition-transform duration-200" :class="{'rotate-180': open}" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>
                <div x-show="open" x-transition class="bg-[#101010]">
                    @forelse($navTemplates as $template)
                        <a href="{{ route('templates.index') }}#template-{{ $template->id }}"
                           class="block w-full ps-3 pe-4 py-2 border-l-4 border-transparent text-base font-medium text-gray-400 hover:text-gray-100 hover:bg-gray-700 hover:border-gray-500 focus:outline-none transition duration-150 ease-in-out">
                            {{ $template->name }}
                        </a>
                    @empty
                        <span class="block ps-6 pe-4 py-2 text-sm text-gray-500 italic">No templates</span>
                    @endforelse
                </div>
            </div>
            @auth
            <!-- Notifications (the bell's feed, inline) -->
            <div x-data="notificationsMenu">
                <button @click="toggle()" class="w-full flex items-center justify-between ps-3 pe-4 py-2 border-l-4 border-transparent text-base font-medium text-gray-400 hover:text-gray-100 hover:bg-gray-700 hover:border-gray-500 focus:outline-none focus:text-gray-100 focus:bg-gray-700 transition duration-150 ease-in-out">
                    <span class="flex items-center gap-2">
                        {{ __('Notifications') }}
                        <span data-notif-badge="count" @class([
                                'items-center justify-center min-w-[1.25rem] h-5 px-1 text-xs font-bold text-white bg-red-500 rounded-full',
                                'inline-flex' => $unreadNotifications > 0,
                                'hidden' => $unreadNotifications <= 0,
                              ])>{{ $unreadNotifications > 9 ? '9+' : $unreadNotifications }}</span>
                    </span>
                    <svg class="h-5 w-5 transform transition-transform duration-200" :class="{'rotate-180': open}" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>
                <div x-show="open" x-transition class="bg-[#101010] max-h-96 overflow-y-auto" style="display: none;">
                    <template x-if="!loaded">
                        <div class="px-4 py-6 text-center text-sm text-gray-500">Loading…</div>
                    </template>
                    <template x-if="loaded">
                        <div x-ref="notifHtml"></div>
                    </template>
                </div>
            </div>
            @endauth
            <x-responsive-nav-link :href="route('changelogs.user')" :active="request()->routeIs('changelogs.*')">
                {{ __('Activity') }}
            </x-responsive-nav-link>

            @if($otherLinksFiles->isNotEmpty())
                <!-- Other Links Collapsible Section -->
                <div x-data="dropdown" class="border-t border-gray-700">
                    <button @click="open = !open" class="w-full flex items-center justify-between px-4 py-2 text-base font-medium text-gray-400 hover:text-gray-100 hover:bg-gray-700 focus:outline-none focus:text-gray-100 focus:bg-gray-700 transition duration-150 ease-in-out">
                        <span>{{ __('Other Links') }}</span>
                        <svg class="h-5 w-5 transform transition-transform duration-200" :class="{'rotate-180': open}" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                    <div x-show="open" x-transition class="bg-[#101010]">
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
                <form method="POST" action="{{ route('logout') }}" id="logout-form-mobile">
                    @csrf
                    <button type="button" data-testid="logout-btn" @click="$el.closest('form').requestSubmit()" class="block w-full px-4 py-2 text-start text-base font-medium text-gray-300 hover:bg-gray-700 focus:outline-none focus:bg-gray-700 transition duration-150 ease-in-out">
                        {{ __('Log Out') }}
                    </button>
                </form>
            </div>
        </div>
        @endauth
    </div>
<script nonce="{{ csp_nonce() }}">
document.addEventListener('alpine:init', () => {
    Alpine.data('navSearch', () => ({
        open: false,
        query: '',
        toggle() {
            this.open = !this.open;
            if (this.open) {
                // See taskPanelEditor.startEdit() in layouts/app.blade.php for why this
                // waits for a paint, not just a microtask.
                this.$nextTick(() => requestAnimationFrame(() => this.$refs.searchInput.focus()));
            }
        },
        close() {
            this.open = false;
            this.query = '';
        },
        submit() {
            const q = this.query.trim();
            if (q) {
                window.location.href = '{{ route('search') }}?q=' + encodeURIComponent(q) + '&show_incomplete=1&search_title=1&search_description=1';
            }
        },
        clearQuery() {
            this.query = '';
            this.$refs.searchInput.focus();
        }
    }));

    Alpine.data('notificationsMenu', () => ({
        open: false,
        loaded: false,
        // Refetches on every open, not just the first: the heartbeat can surface new notifications
        // after the menu was last opened, and fetching the feed is what marks them seen.
        async toggle() {
            this.open = !this.open;
            if (this.open) {
                this.loaded = true;
                try {
                    const res = await fetch('{{ route('notifications.feed') }}', {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                        }
                    });
                    const d = await res.json();
                    if (this.$refs.notifHtml) this.$refs.notifHtml.innerHTML = d.html;
                    window.setNotificationBadge(0);
                } catch {}
            }
        }
    }));
});
</script>
</nav>
