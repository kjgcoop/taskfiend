<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2 w-full" x-data="projectHeaderControls">
            {{-- Star / default toggle --}}
            @if($project->is_default)
                <span title="Default project" class="text-yellow-400 shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                    </svg>
                </span>
            @elseif($project->user_id === Auth::id() && $project->status === 'incomplete')
                <button id="set-default-btn"
                        @click="setDefaultProject({{ $project->id }})"
                        title="Set as default project"
                        class="text-gray-600 hover:text-yellow-400 transition-colors shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 20 20" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                    </svg>
                </button>
            @endif

            {{-- Heart / favorite toggle --}}
            @if($project->user_id === Auth::id())
                <button id="heart-btn"
                        @click="toggleHeart({{ $project->id }})"
                        title="{{ $project->is_hearted ? 'Remove from active projects' : 'Mark as active project' }}"
                        data-hearted="{{ $project->is_hearted ? 'true' : 'false' }}"
                        class="shrink-0 transition-colors {{ $project->is_hearted ? 'text-pink-500' : 'text-gray-600 hover:text-pink-500' }}">
                    <svg id="heart-svg" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="{{ $project->is_hearted ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="{{ $project->is_hearted ? '0' : '1.5' }}">
                        <path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd" />
                    </svg>
                </button>
            @endif

            {{-- Inline-editable project name + count badge --}}
            @php $isInactive = in_array($project->status, ['done', 'archived']); @endphp
            <div x-data="projectHeaderEditor"
                 data-project-id="{{ $project->id }}"
                 data-project-name="{{ $project->name }}"
                 class="flex items-center gap-2 min-w-0">
                <h2 x-show="!editing"
                    @click="{{ !$isInactive && $project->user_id === Auth::id() ? 'startEdit()' : '' }}"
                    class="font-semibold text-xl text-gray-100 leading-tight truncate {{ !$isInactive && $project->user_id === Auth::id() ? 'cursor-pointer hover:text-gray-300' : '' }}">
                    <span x-text="name"></span>
                </h2>
                <input x-show="editing" x-cloak x-ref="nameInput"
                       x-model="name"
                       @blur="save()"
                       @keydown.enter.prevent="save()"
                       @keydown.escape.prevent="cancel()"
                       class="font-semibold text-xl text-gray-100 bg-transparent border-b border-gray-400 focus:outline-none focus:border-blue-400 min-w-0 w-64" />
                <x-task-count-badge :count="$tasks->count()" :breakdown="$breakdown" />
            </div>

            {{-- Spacer --}}
            <div class="flex-1"></div>

            {{-- ID + copy link --}}
            <span class="text-sm text-gray-500 shrink-0">#{{ $project->id }}</span>
            <span x-data="copyButton" class="shrink-0">
                <button @click="copy('{{ route('projects.show', $project) }}')"
                        title="Copy link"
                        class="text-gray-500 hover:text-gray-300 transition-colors">
                    <span x-show="!copied">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                        </svg>
                    </span>
                    <span x-show="copied" x-cloak class="text-xs text-green-400">Copied!</span>
                </button>
            </span>

            {{-- Status log button --}}
            @php
                $statusTooltip = $project->statusLogs->count() > 0
                    ? Str::limit(strip_tags($project->statusLogs->first()->body), 100)
                    : 'Status log';
            @endphp
            <button @click="openStatusModal()"
                    title="{{ $statusTooltip }}"
                    class="relative shrink-0 p-2 text-gray-400 hover:text-gray-100 hover:bg-gray-700 rounded transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                </svg>
                @if($project->statusLogs->count() > 0)
                    <span class="absolute -top-0.5 -right-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-gray-600 text-[10px] text-gray-300">{{ $project->statusLogs->count() }}</span>
                @endif
            </button>

            {{-- Three-dot menu --}}
            <div class="relative shrink-0" @click.outside="closeMenu()">
                <button @click="toggleMenu()"
                        class="p-2 text-gray-400 hover:text-gray-100 hover:bg-gray-700 rounded transition-colors"
                        title="More options">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M10 6a2 2 0 110-4 2 2 0 010 4zm0 6a2 2 0 110-4 2 2 0 010 4zm0 6a2 2 0 110-4 2 2 0 010 4z" />
                    </svg>
                </button>
                <div x-show="showMenu" x-cloak
                     class="absolute right-0 mt-1 w-48 bg-gray-800 border border-gray-600 rounded shadow-lg z-10">
                    @if($project->user_id === Auth::id())
                        <button @click="openDetails()"
                                class="w-full text-left px-4 py-2 text-gray-200 hover:bg-gray-700">
                            Details
                        </button>
                        <button @click="openReminder()"
                                class="w-full text-left px-4 py-2 text-gray-200 hover:bg-gray-700">
                            Set Reminder
                        </button>
                    @endif
                    <a href="{{ route('projects.export-markdown', $project) }}"
                       class="block px-4 py-2 text-gray-200 hover:bg-gray-700">
                        Export .md
                    </a>
                    @if($project->user_id === Auth::id())
                        <a href="{{ route('projects.import-markdown', $project) }}"
                           class="block px-4 py-2 text-gray-200 hover:bg-gray-700">
                            Import Updates from .md
                        </a>
                    @endif
                    <a href="{{ route('projects.export-template', $project) }}"
                       class="block px-4 py-2 text-gray-200 hover:bg-gray-700">
                        Download Template
                    </a>
                    @if($project->user_id === Auth::id())
                        <button @click="openSaveTemplate()"
                                class="w-full text-left px-4 py-2 text-gray-200 hover:bg-gray-700">
                            Save as Template
                        </button>
                    @endif
                    <form method="POST" action="{{ route('projects.duplicate', $project) }}">
                        @csrf
                        <button type="submit"
                                class="w-full text-left px-4 py-2 text-gray-200 hover:bg-gray-700">
                            Duplicate Project
                        </button>
                    </form>
                </div>
            </div>

            {{-- Save as Template Modal --}}
            <div x-show="showSaveTemplate" x-cloak
                 class="fixed inset-0 z-50 flex items-center justify-center bg-black/60"
                 @keydown.escape.window="closeSaveTemplate()">
                <div class="bg-gray-800 border border-gray-600 rounded-lg p-6 w-full max-w-md shadow-xl"
                     @click.stop>
                    <h4 class="text-gray-100 font-semibold mb-4">Save as Template</h4>
                    <form method="POST" action="{{ route('templates.store', $project) }}">
                        @csrf
                        <div class="mb-4">
                            <label class="block text-sm text-gray-300 mb-1" for="template_name">Template Name</label>
                            <input id="template_name" type="text" name="template_name"
                                   value="{{ $project->name }}"
                                   required
                                   class="w-full bg-gray-700 border border-gray-600 text-gray-100 rounded px-3 py-2 text-sm focus:outline-none focus:border-blue-500">
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm text-gray-300 mb-1" for="template_description">Description <span class="text-gray-500">(optional)</span></label>
                            <textarea id="template_description" name="template_description" rows="2"
                                      class="w-full bg-gray-700 border border-gray-600 text-gray-100 rounded px-3 py-2 text-sm focus:outline-none focus:border-blue-500 placeholder-gray-500"
                                      placeholder="What is this template for?"></textarea>
                        </div>
                        <div class="mb-5 flex items-center gap-2">
                            <input id="is_public" type="checkbox" name="is_public" value="1"
                                   class="rounded border-gray-600 bg-gray-700 text-blue-600 focus:ring-blue-500">
                            <label for="is_public" class="text-sm text-gray-300">Make public (visible to all users)</label>
                        </div>
                        <p class="text-xs text-gray-500 mb-4">Only incomplete tasks are included in the template.</p>
                        <div class="flex justify-end gap-2">
                            <button type="button" @click="closeSaveTemplate()"
                                    class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-gray-300 rounded text-sm">
                                Cancel
                            </button>
                            <button type="submit"
                                    class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded text-sm">
                                Save Template
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            {{-- Reminder Modal --}}
            @if($project->user_id === Auth::id() && !$isInactive)
            <div x-show="showReminder" x-cloak
                 class="fixed inset-0 z-50 flex items-center justify-center bg-black/60"
                 @keydown.escape.window="closeReminder()">
                <div class="bg-gray-800 border border-gray-600 rounded-lg p-6 w-full max-w-md shadow-xl"
                     @click.stop>
                    <div class="flex justify-between items-center mb-5">
                        <h4 class="text-gray-100 font-semibold text-lg">Set Reminder</h4>
                        <button @click="closeReminder()" class="text-gray-400 hover:text-gray-100">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    @if($activeReminder)
                        <div class="flex items-center justify-between mb-4 p-2 bg-blue-950/40 border border-blue-700/50 rounded text-sm">
                            <span class="text-blue-300">
                                {{ \Carbon\Carbon::parse($activeReminder->date)->format('M j, Y') }}
                                @if($activeReminder->recurrence_pattern)
                                    &middot; <span class="text-blue-400/70">{{ $activeReminder->recurrence_pattern }}</span>
                                @endif
                            </span>
                            <form method="POST" action="{{ route('projects.reminders.destroy', [$project, $activeReminder]) }}">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-xs text-gray-500 hover:text-red-400 transition-colors ml-3">Remove</button>
                            </form>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('projects.reminders.store', $project) }}"
                          class="space-y-3"
                          x-data="reminderDateInput" data-initial-date="{{ old('date', $activeReminder?->date) }}">
                        @csrf
                        <div>
                            <div class="flex gap-2 items-center">
                                <input type="text" x-model="dateText"
                                       @input.debounce.300ms="previewDate()"
                                       placeholder="today, June 5, next Friday…"
                                       class="flex-1 rounded-md bg-gray-700 border-gray-600 text-gray-100 placeholder-gray-500 shadow-sm focus:border-blue-500 focus:ring-blue-500 px-3 py-2 text-sm">
                                <div class="relative shrink-0">
                                    <button type="button" @click="$refs.calPicker.showPicker()"
                                            class="p-2 bg-gray-700 border border-gray-600 rounded-md hover:bg-gray-600 text-gray-400 hover:text-gray-200"
                                            title="Open calendar">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                    </button>
                                    <input type="date" x-ref="calPicker" @change="pickDate($event.target.value)"
                                           class="absolute inset-0 opacity-0 w-full h-full cursor-pointer">
                                </div>
                            </div>
                            <input type="hidden" name="date" :value="resolvedDate">
                            <p x-show="datePreview" x-text="datePreview" class="mt-1 text-xs text-green-400"></p>
                            <p x-show="dateError" x-text="dateError" class="mt-1 text-xs text-red-400"></p>
                        </div>
                        <input type="text" name="recurrence_pattern" value="{{ old('recurrence_pattern', $activeReminder?->recurrence_pattern) }}"
                               placeholder="Recurrence (e.g. weekly, every Thursday)"
                               class="w-full rounded-md bg-gray-700 border-gray-600 text-gray-100 placeholder-gray-500 shadow-sm focus:border-blue-500 focus:ring-blue-500 px-3 py-2 text-sm">
                        @error('recurrence_pattern')<p class="text-xs text-red-400">{{ $message }}</p>@enderror
                        <label class="flex items-center gap-2 text-sm text-gray-400">
                            <input type="checkbox" name="recurrence_floating" value="1"
                                   {{ old('recurrence_floating', $activeReminder?->recurrence_floating) ? 'checked' : '' }}
                                   class="rounded border-gray-600 bg-gray-700 text-blue-600 focus:ring-blue-500">
                            Floating (recur from completion date)
                        </label>
                        <div class="flex justify-end gap-2 pt-1">
                            <button type="button" @click="closeReminder()"
                                    class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-gray-300 rounded text-sm">
                                Cancel
                            </button>
                            <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded">
                                {{ $activeReminder ? 'Update Reminder' : 'Set Reminder' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            @endif

            {{-- Status Log Modal --}}
            <div x-show="showStatus" x-cloak
                 class="fixed inset-0 z-50 flex items-center justify-center bg-black/60"
                 @keydown.escape.window="closeStatusModal()">
                <div class="bg-gray-800 border border-gray-600 rounded-lg w-full max-w-lg shadow-xl flex flex-col h-[520px]"
                     @click.stop>
                    <div class="flex items-center justify-between px-6 pt-2 pb-2 border-b border-gray-700 shrink-0">
                        <div class="flex gap-1">
                            @if(!$isInactive)
                            <button @click="setStatusTabPost()"
                                    :class="statusTab === 'post' ? 'bg-gray-700 text-gray-100' : 'text-gray-400 hover:text-gray-200'"
                                    class="px-3 py-1.5 rounded text-sm font-medium transition-colors">
                                Post Update
                            </button>
                            @endif
                            <button @click="setStatusTabHistory()"
                                    :class="statusTab === 'history' ? 'bg-gray-700 text-gray-100' : 'text-gray-400 hover:text-gray-200'"
                                    class="px-3 py-1.5 rounded text-sm font-medium transition-colors">
                                History
                                @if($project->statusLogs->count() > 0)
                                    <span class="ml-1 px-1.5 py-0.5 text-xs rounded-full bg-gray-700 text-gray-400">{{ $project->statusLogs->count() }}</span>
                                @endif
                            </button>
                        </div>
                        <button @click="closeStatusModal()" class="text-gray-400 hover:text-gray-100">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    {{-- Post Update tab --}}
                    @if(!$isInactive)
                    <div x-show="statusTab === 'post'" class="flex flex-col flex-1 overflow-hidden p-6">
                        <div class="overflow-y-auto flex-1 mb-4">
                            @if($project->statusLogs->count() > 0)
                                @php $latest = $project->statusLogs->first(); @endphp
                                <div class="p-3 bg-gray-900/60 border border-gray-700 rounded-lg">
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="text-xs font-medium text-gray-400">{{ $latest->user->name }}</span>
                                        <span class="text-xs text-gray-600">{{ $latest->created_at->diffForHumans() }}</span>
                                    </div>
                                    <div class="markdown-body text-sm text-gray-400">{!! render_body($latest->body) !!}</div>
                                </div>
                            @endif
                        </div>
                        <form method="POST" action="{{ route('projects.statusLogs.store', $project) }}" class="shrink-0">
                            @csrf
                            <textarea name="body" rows="4" required
                                      placeholder="What's the current status?"
                                      class="w-full rounded-md bg-gray-700 border-gray-600 text-gray-100 placeholder-gray-500 shadow-sm focus:border-blue-500 focus:ring-blue-500 px-3 py-2 text-sm resize-none"></textarea>
                            <div class="flex justify-end mt-3 gap-2">
                                <button type="button" @click="closeStatusModal()"
                                        class="px-3 py-1.5 bg-gray-700 hover:bg-gray-600 text-gray-300 rounded text-sm">
                                    Cancel
                                </button>
                                <button type="submit"
                                        class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded text-sm">
                                    Post
                                </button>
                            </div>
                        </form>
                    </div>
                    @endif

                    {{-- History tab --}}
                    <div x-show="statusTab === 'history'" class="overflow-y-auto flex-1 p-6">
                        @if($project->statusLogs->count() > 0)
                            <div class="space-y-4">
                                @foreach($project->statusLogs as $log)
                                    <div class="border-b border-gray-700 pb-4 last:border-0 last:pb-0">
                                        <div class="flex items-center justify-between mb-1">
                                            <div class="flex items-center gap-2">
                                                <span class="text-sm font-medium text-gray-300">{{ $log->user->name }}</span>
                                                <span class="text-xs text-gray-500">{{ $log->created_at->diffForHumans() }}</span>
                                            </div>
                                            @if($log->user_id === Auth::id() || $project->user_id === Auth::id())
                                                <form method="POST" action="{{ route('projects.statusLogs.destroy', [$project, $log]) }}">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="text-xs text-gray-600 hover:text-red-400 transition-colors">Delete</button>
                                                </form>
                                            @endif
                                        </div>
                                        <div class="markdown-body text-sm text-gray-400">{!! render_body($log->body) !!}</div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-sm text-gray-600 italic">No status updates yet.</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </x-slot>

    @php $isInactive = in_array($project->status, ['done', 'archived']); @endphp
    <div class="py-12 relative" @if($project->user_id === Auth::id()) x-data="projectEditor" data-project-id="{{ $project->id }}" @endif
         @if($project->user_id === Auth::id()) @open-project-details.window="openDetails()" @endif>
        @if($project->background_image)
            <div class="absolute inset-0" style="background-image: url('{{ route('projects.background', $project) }}'); background-attachment: fixed; background-position: center; background-size: cover; background-repeat: no-repeat;"></div>
            <div class="absolute inset-0 bg-black/65"></div>
        @endif
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6 relative">
            @if($project->status === 'archived')
                <div class="bg-amber-950/40 border border-amber-700/60 rounded-lg p-4 flex items-center gap-3">
                    <span class="text-amber-500 text-xl">⊘</span>
                    <div>
                        <p class="text-amber-400 font-semibold">This project is archived{{ $statusChangedAt ? ' on ' . $statusChangedAt->format('l, F j, Y') : '' }}</p>
                        <p class="text-amber-600 text-sm mt-0.5">Tasks in this project are hidden from your task lists.</p>
                    </div>
                </div>
            @elseif($project->status === 'done')
                <div class="bg-green-950/40 border border-green-700/60 rounded-lg p-4 flex items-center gap-3">
                    <span class="text-green-500 text-xl">✓</span>
                    <div>
                        <p class="text-green-400 font-semibold">This project is done{{ $statusChangedAt ? ' as of ' . $statusChangedAt->format('l, F j, Y') : '' }}</p>
                        <p class="text-green-600 text-sm mt-0.5">Tasks in this project are hidden from your task lists.</p>
                    </div>
                </div>
            @elseif($project->end_date)
                @php
                    $daysUntil = (int) now()->startOfDay()->diffInDays($project->end_date, false);
                    $verb = $project->auto_close_action === 'done' ? 'marked done' : 'archived';
                    $verbFuture = $project->auto_close_action === 'done' ? 'Mark done' : 'Archive';
                    if ($daysUntil < 0) {
                        $urgency = ['bg' => 'bg-red-950/40', 'border' => 'border-red-700/60', 'icon' => 'text-red-400', 'head' => 'text-red-300', 'sub' => 'text-red-500'];
                        $label = 'Past end date — will be ' . $verb . ' overnight';
                    } elseif ($daysUntil === 0) {
                        $urgency = ['bg' => 'bg-orange-950/40', 'border' => 'border-orange-600/60', 'icon' => 'text-orange-400', 'head' => 'text-orange-300', 'sub' => 'text-orange-500'];
                        $label = 'Scheduled to be ' . $verb . ' tonight';
                    } elseif ($daysUntil <= 7) {
                        $urgency = ['bg' => 'bg-orange-950/30', 'border' => 'border-orange-700/50', 'icon' => 'text-orange-500', 'head' => 'text-orange-400', 'sub' => 'text-orange-600'];
                        $label = $verbFuture . ' in ' . $daysUntil . ' ' . Str::plural('day', $daysUntil) . ' — ' . $project->end_date->format('F j');
                    } else {
                        $urgency = ['bg' => 'bg-gray-800/60', 'border' => 'border-gray-600/60', 'icon' => 'text-gray-400', 'head' => 'text-gray-300', 'sub' => 'text-gray-500'];
                        $label = 'Scheduled to be ' . $verb . ' on ' . $project->end_date->format('F j, Y');
                    }
                @endphp
                <div class="{{ $urgency['bg'] }} border {{ $urgency['border'] }} rounded-lg p-4 flex items-center gap-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0 {{ $urgency['icon'] }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <p class="text-sm {{ $urgency['head'] }}">{{ $label }}</p>
                </div>
            @endif

            {{-- Reminder banner --}}
            @if($activeReminder)
                @php
                    $reminderDays = (int) now()->startOfDay()->diffInDays(\Carbon\Carbon::parse($activeReminder->date), false);
                @endphp
                <div class="bg-blue-950/30 border border-blue-700/50 rounded-lg p-4 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                        <p class="text-sm text-blue-300">
                            @if($reminderDays < 0)
                                Reminder was set for {{ \Carbon\Carbon::parse($activeReminder->date)->format('M j') }}
                            @elseif($reminderDays === 0)
                                Reminder for today
                            @else
                                Reminder in {{ $reminderDays }} {{ Str::plural('day', $reminderDays) }} &mdash; {{ \Carbon\Carbon::parse($activeReminder->date)->format('M j') }}
                            @endif
                            @if($activeReminder->recurrence_pattern)
                                <span class="text-blue-400/60 text-xs ml-1">({{ $activeReminder->recurrence_pattern }})</span>
                            @endif
                        </p>
                    </div>
                    <form method="POST" action="{{ route('projects.reminders.dismiss', [$project, $activeReminder]) }}">
                        @csrf
                        <button type="submit" class="text-xs text-blue-400/60 hover:text-blue-300 transition-colors whitespace-nowrap">Cancel reminder</button>
                    </form>
                </div>
            @endif

            {{-- Details Modal (triggered by "Details" in the three-dot menu) --}}
            @if($project->user_id === Auth::id())
            <div x-show="showDetails" x-cloak
                 class="fixed inset-0 z-50 flex items-center justify-center bg-black/60"
                 @keydown.escape.window="closeDetails()">
                <div class="bg-gray-800 border border-gray-600 rounded-lg p-6 w-full max-w-lg shadow-xl overflow-y-auto max-h-[90vh]"
                     @click.stop>
                    <div class="flex justify-between items-center mb-5">
                        <h4 class="text-gray-100 font-semibold text-lg">Project Details</h4>
                        <button @click="closeDetails()" class="text-gray-400 hover:text-gray-100">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    {{-- Name --}}
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-400 mb-1">Project Name</label>
                        @if(!$isInactive)
                            <div class="flex gap-2">
                                <input type="text" x-model="fields.name"
                                       @keydown.enter="saveField('name')"
                                       class="flex-1 rounded-md bg-gray-700 border-gray-600 text-gray-100 placeholder-gray-500 shadow-sm focus:border-blue-500 focus:ring-blue-500 px-3 py-2 text-sm">
                                <button @click="saveField('name')"
                                        class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                                    Save
                                </button>
                            </div>
                        @else
                            <p class="text-gray-300 px-1">{{ $project->name }}</p>
                        @endif
                    </div>

                    {{-- Description --}}
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-400 mb-1">Description</label>
                        @if(!$isInactive)
                            <textarea x-model="fields.description" rows="4"
                                      @keydown.ctrl.enter="saveField('description')"
                                      class="w-full rounded-md bg-gray-700 border-gray-600 text-gray-100 placeholder-gray-500 shadow-sm focus:border-blue-500 focus:ring-blue-500 px-3 py-2 text-sm"
                                      placeholder="Add a description..."></textarea>
                            <div class="flex items-center justify-between mt-1">
                                <p class="text-xs text-gray-500">Ctrl+Enter to save</p>
                                <button @click="saveField('description')"
                                        class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                                    Save
                                </button>
                            </div>
                        @else
                            <div class="markdown-body text-gray-300 px-1">
                                @if($project->description)
                                    {!! render_body($project->description) !!}
                                @else
                                    <p class="text-gray-500 italic">No description</p>
                                @endif
                            </div>
                        @endif
                    </div>

                    {{-- Status --}}
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-400 mb-1">Status</label>
                        @if(!$isInactive)
                            <div class="flex gap-2">
                                <select x-model="fields.status"
                                        class="flex-1 rounded-md bg-gray-700 border-gray-600 text-gray-100 shadow-sm focus:border-blue-500 focus:ring-blue-500 px-3 py-2 text-sm">
                                    <option value="incomplete">Incomplete</option>
                                    <option value="done">Done</option>
                                    <option value="archived">Archived</option>
                                </select>
                                <button @click="saveField('status')"
                                        class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                                    Save
                                </button>
                            </div>
                        @else
                            <div class="flex gap-2">
                                <select x-model="fields.status"
                                        class="flex-1 rounded-md bg-gray-700 border-gray-600 text-gray-100 shadow-sm focus:border-blue-500 focus:ring-blue-500 px-3 py-2 text-sm">
                                    <option value="incomplete">Incomplete</option>
                                    <option value="done">Done</option>
                                    <option value="archived">Archived</option>
                                </select>
                                <button @click="saveField('status')"
                                        class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                                    Save
                                </button>
                            </div>
                        @endif
                    </div>

                    <hr class="p-1">

                    {{-- End Date --}}
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-400 mb-1">End Date <span class="text-gray-500 font-normal">(optional)</span></label>
                        @if(!$isInactive)
                            <div class="flex gap-2">
                                <input type="date" x-model="fields.end_date"
                                       class="flex-1 rounded-md bg-gray-700 border-gray-600 text-gray-100 shadow-sm focus:border-blue-500 focus:ring-blue-500 px-3 py-2 text-sm">
                                <button @click="saveField('end_date')"
                                        class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                                    Save
                                </button>
                            </div>
                        @else
                            <p class="text-gray-300 px-1">{{ $project->end_date ? $project->end_date->format('F j, Y') : '—' }}</p>
                        @endif
                    </div>

                    {{-- Auto-close action --}}
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-400 mb-1">When end date passes</label>
                        @if(!$isInactive)
                            <div class="flex gap-2">
                                <select x-model="fields.auto_close_action"
                                        class="flex-1 rounded-md bg-gray-700 border-gray-600 text-gray-100 shadow-sm focus:border-blue-500 focus:ring-blue-500 px-3 py-2 text-sm">
                                    <option value="archived">Archive the project</option>
                                    <option value="done">Mark the project as done</option>
                                </select>
                                <button @click="saveField('auto_close_action')"
                                        class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                                    Save
                                </button>
                            </div>
                        @else
                            <p class="text-gray-300 px-1">{{ $project->auto_close_action === 'done' ? 'Mark as done' : 'Archive' }}</p>
                        @endif
                    </div>


                    {{-- Assignees --}}
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-400 mb-1">Assigned To</label>
                        @if(!$isInactive)
                            <div class="space-y-2 mb-2 max-h-40 overflow-y-auto border border-gray-600 bg-[#101010] rounded p-3">
                                @foreach($users as $user)
                                    <label class="flex items-center {{ $user->id === $project->user_id ? 'opacity-50 cursor-not-allowed' : '' }}">
                                        <input type="checkbox" value="{{ $user->id }}" x-model="fields.assignee_ids"
                                               class="rounded border-gray-600 bg-gray-700 text-blue-600 focus:ring-blue-500"
                                               {{ $user->id === $project->user_id ? 'disabled' : '' }}>
                                        <span class="ml-2 text-sm text-gray-300">{{ $user->name }} ({{ $user->email }})</span>
                                    </label>
                                @endforeach
                            </div>
                            <button @click="saveField('assignee_ids')"
                                    class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                                Save Assignees
                            </button>
                        @else
                            @if($project->assignees->count() > 0)
                                @foreach($project->assignees as $a)
                                    <p class="text-sm text-gray-300">{{ $a->name }}</p>
                                @endforeach
                            @else
                                <p class="text-gray-500 italic text-sm">No assignees</p>
                            @endif
                        @endif
                    </div>

                    {{-- Background Image --}}
                    @if(!$isInactive)
                    <div class="mb-4" x-data="{ showUpload: false }">
                        <label class="block text-sm font-medium text-gray-400 mb-1">Background Image</label>
                        @if($project->background_image)
                            <img src="{{ route('projects.background', $project) }}"
                                 alt="Project background"
                                 class="rounded-md border border-gray-600 mb-2"
                                 style="height: 80px; width: auto;">
                            <div class="flex items-center gap-3">
                                <button @click="showUpload = !showUpload"
                                        class="text-sm text-gray-400 hover:text-gray-200 underline">
                                    Replace
                                </button>
                                <form method="POST" action="{{ route('projects.background.remove', $project) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-sm text-red-400 hover:text-red-300 underline">
                                        Remove
                                    </button>
                                </form>
                            </div>
                        @else
                            <button @click="showUpload = !showUpload"
                                    class="text-sm text-gray-400 hover:text-gray-200 underline">
                                Add background image
                            </button>
                        @endif
                        <div x-show="showUpload" x-cloak class="mt-2">
                            <form method="POST" action="{{ route('projects.background.upload', $project) }}"
                                  enctype="multipart/form-data" class="flex items-center gap-2 flex-wrap">
                                @csrf
                                <input type="file" name="background_image" required
                                       accept="image/jpeg,image/png,image/webp,image/gif,image/avif,image/heic,image/heif,.jpg,.jpeg,.png,.webp,.gif,.avif,.heic,.heif"
                                       class="block text-sm text-gray-400 file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-gray-700 file:text-gray-100 hover:file:bg-gray-600 bg-[#101010] border border-gray-600 rounded-md">
                                <button type="submit"
                                        class="px-3 py-1.5 bg-blue-600 text-white text-xs rounded hover:bg-blue-700 whitespace-nowrap">
                                    Upload
                                </button>
                            </form>
                            <p class="mt-1 text-xs text-gray-500">JPG, PNG, WebP, GIF, AVIF, HEIC &mdash; max 20 MB</p>
                        </div>
                    </div>
                    @endif

                    {{-- Created By (read-only) --}}
                    <div class="mb-5">
                        <label class="block text-sm font-medium text-gray-400 mb-1">Created By</label>
                        <p class="text-sm text-gray-300">{{ $project->creator->name }}</p>
                    </div>

                    <div x-show="fieldError" x-cloak class="mb-4 p-3 bg-red-900/50 border border-red-700 rounded text-sm text-red-300" x-text="fieldError"></div>

                    <div class="flex justify-end">
                        <button @click="closeDetails()"
                                class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-gray-300 rounded text-sm">
                            Close
                        </button>
                    </div>
                </div>
            </div>
            @endif

            @if(false) {{-- Old project details card — removed, content moved to Details modal --}}
            <div>
                @php $isInactive = false; @endphp

                @if($project->user_id === Auth::id())
                    <!-- Project name -->
                    <div class="mb-4">
                        <span class="text-sm font-medium text-gray-500">Project Name</span>
                        @if(!$isInactive)
                            <div @click="startEdit('name')" x-show="!editing.name" class="mt-1 cursor-pointer hover:bg-gray-700 p-2 rounded">
                                <p class="text-lg font-semibold text-gray-100">{{ $project->name }}</p>
                            </div>
                            <div x-show="editing.name" class="mt-1">
                                <input type="text" x-model="fields.name"
                                       @keydown.enter="saveField('name')"
                                       @keydown.escape="cancelEdit('name')"
                                       class="w-full rounded-md bg-gray-700 border-gray-600 text-gray-100 placeholder-gray-500 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <div class="flex gap-2 mt-2">
                                    <button @click="saveField('name')"
                                            class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                                        Save
                                    </button>
                                    <button @click="cancelEdit('name')"
                                            class="px-3 py-1 bg-gray-700 text-gray-300 text-sm rounded hover:bg-gray-600">
                                        Cancel
                                    </button>
                                </div>
                            </div>
                        @else
                            <div class="mt-1 p-2">
                                <p class="text-lg font-semibold text-gray-100">{{ $project->name }}</p>
                            </div>
                        @endif
                    </div>

                    <!-- Description -->
                    <div class="mt-4">
                        <span class="text-sm font-medium text-gray-500">Description</span>
                        @if(!$isInactive)
                            <div @click="startEdit('description')" x-show="!editing.description" class="markdown-body mt-1 cursor-pointer hover:bg-gray-700 p-2 rounded min-h-[40px]">
                                @if($project->description)
                                    {!! render_body($project->description) !!}
                                @else
                                    <p class="text-gray-400 italic">Click to add description</p>
                                @endif
                            </div>
                            <div x-show="editing.description" class="mt-1">
                                <textarea x-model="fields.description" rows="3"
                                          @keydown.ctrl.enter="saveField('description')"
                                          @keydown.escape="cancelEdit('description')"
                                          class="w-full rounded-md bg-gray-700 border-gray-600 text-gray-100 placeholder-gray-500 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                          placeholder="Add a description..."></textarea>
                                <p class="mt-1 text-xs text-gray-500">Ctrl+Enter to save, Escape to cancel</p>
                                <div class="flex gap-2 mt-2">
                                    <button @click="saveField('description')"
                                            class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                                        Save
                                    </button>
                                    <button @click="cancelEdit('description')"
                                            class="px-3 py-1 bg-gray-700 text-gray-300 text-sm rounded hover:bg-gray-600">
                                        Cancel
                                    </button>
                                </div>
                            </div>
                        @else
                            <div class="markdown-body mt-1 p-2 min-h-[40px]">
                                @if($project->description)
                                    {!! render_body($project->description) !!}
                                @else
                                    <p class="text-gray-600 italic">No description</p>
                                @endif
                            </div>
                        @endif
                    </div>

                    <!-- Read more: Status, Assignees, Background Image -->
                    <details class="mt-4 group">
                        <summary class="cursor-pointer text-sm text-blue-400 hover:text-blue-300 list-none flex items-center gap-1">
                            <span class="group-open:hidden">Read more</span>
                            <span class="hidden group-open:inline">Show less</span>
                        </summary>
                        <div class="mt-4 space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <!-- Status (editable) -->
                                <div>
                                    <span class="text-sm font-medium text-gray-500">Status</span>
                                    <div @click="startEdit('status')" x-show="!editing.status" class="mt-1 cursor-pointer hover:bg-gray-700 p-2 rounded">
                                        <span class="inline-block px-2 py-1 text-xs rounded
                                            @if($project->status === 'done') bg-green-100 text-green-800
                                            @elseif($project->status === 'archived') bg-gray-100 text-gray-800
                                            @else bg-blue-100 text-blue-800 @endif">
                                            {{ ucfirst($project->status) }}
                                        </span>
                                    </div>
                                    <div x-show="editing.status" class="mt-1">
                                        <select x-model="fields.status"
                                                @keydown.escape="cancelEdit('status')"
                                                class="w-full rounded-md bg-gray-700 border-gray-600 text-gray-100 placeholder-gray-500 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                            <option value="incomplete">Incomplete</option>
                                            <option value="done">Done</option>
                                            <option value="archived">Archived</option>
                                        </select>
                                        <div class="flex gap-2 mt-2">
                                            <button @click="saveField('status')"
                                                    class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                                                Save
                                            </button>
                                            <button @click="cancelEdit('status')"
                                                    class="px-3 py-1 bg-gray-700 text-gray-300 text-sm rounded hover:bg-gray-600">
                                                Cancel
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Created By (read-only) -->
                                <div>
                                    <span class="text-sm font-medium text-gray-500">Created By</span>
                                    <p class="mt-1 text-gray-300">{{ $project->creator->name }}</p>
                                </div>
                            </div>

                            <!-- Assignees -->
                            <div>
                                <span class="text-sm font-medium text-gray-500">Assigned To</span>
                                @if(!$isInactive)
                                    <div @click="startEdit('assignee_ids')" x-show="!editing.assignee_ids" class="mt-1 cursor-pointer hover:bg-gray-700 p-2 rounded min-h-[40px]">
                                        @if($project->assignees->count() > 0)
                                            <div class="space-y-1">
                                                @foreach($project->assignees as $assignee)
                                                    <p class="text-sm text-gray-300">{{ $assignee->name }}</p>
                                                @endforeach
                                            </div>
                                        @else
                                            <p class="text-gray-400 italic">Click to add assignees</p>
                                        @endif
                                    </div>
                                    <div x-show="editing.assignee_ids" class="mt-1">
                                        <div class="space-y-2 mb-2 max-h-48 overflow-y-auto border border-gray-600 bg-[#101010] rounded p-3">
                                            @foreach($users as $user)
                                                <label class="flex items-center {{ $user->id === $project->user_id ? 'opacity-50 cursor-not-allowed' : '' }}">
                                                    <input type="checkbox" value="{{ $user->id }}" x-model="fields.assignee_ids"
                                                           class="rounded border-gray-600 bg-gray-700 text-blue-600 focus:ring-blue-500"
                                                           {{ $user->id === $project->user_id ? 'disabled' : '' }}>
                                                    <span class="ml-2 text-sm text-gray-300">{{ $user->name }} ({{ $user->email }})</span>
                                                </label>
                                            @endforeach
                                        </div>
                                        <div class="flex gap-2 mt-2">
                                            <button @click="saveField('assignee_ids')"
                                                    class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                                                Save
                                            </button>
                                            <button @click="cancelEdit('assignee_ids')"
                                                    class="px-3 py-1 bg-gray-700 text-gray-300 text-sm rounded hover:bg-gray-600">
                                                Cancel
                                            </button>
                                        </div>
                                    </div>
                                @else
                                    <div class="mt-1 p-2 min-h-[40px]">
                                        @if($project->assignees->count() > 0)
                                            <div class="space-y-1">
                                                @foreach($project->assignees as $assignee)
                                                    <p class="text-sm text-gray-300">{{ $assignee->name }}</p>
                                                @endforeach
                                            </div>
                                        @else
                                            <p class="text-gray-600 italic">No assignees</p>
                                        @endif
                                    </div>
                                @endif
                            </div>

                            <!-- Background Image (only editable when project is active) -->
                            @if(!$isInactive)
                            <div x-data="{ showUpload: false }">
                                <span class="text-sm font-medium text-gray-500">Background Image</span>
                                @if($project->background_image)
                                    <div class="mt-1">
                                        <div class="mb-2">
                                            <img src="{{ route('projects.background', $project) }}"
                                                 alt="Project background"
                                                 class="rounded-md border border-gray-600"
                                                 style="height: 100px; width: auto;">
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <button @click="showUpload = !showUpload"
                                                    class="text-sm text-gray-400 hover:text-gray-200 underline">
                                                Replace
                                            </button>
                                            <form method="POST" action="{{ route('projects.background.remove', $project) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-sm text-red-400 hover:text-red-300 underline">
                                                    Remove
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                @else
                                    <div class="mt-1">
                                        <button @click="showUpload = !showUpload"
                                                class="text-sm text-gray-400 hover:text-gray-200 underline">
                                            Add background image
                                        </button>
                                    </div>
                                @endif
                                <div x-show="showUpload" x-cloak class="mt-2">
                                    <form method="POST" action="{{ route('projects.background.upload', $project) }}"
                                          enctype="multipart/form-data" class="flex items-center gap-2 flex-wrap">
                                        @csrf
                                        <input type="file" name="background_image" required
                                               accept="image/jpeg,image/png,image/webp,image/gif,image/avif,image/heic,image/heif,.jpg,.jpeg,.png,.webp,.gif,.avif,.heic,.heif"
                                               class="block text-sm text-gray-400 file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-gray-700 file:text-gray-100 hover:file:bg-gray-600 bg-[#101010] border border-gray-600 rounded-md">
                                        <button type="submit"
                                                class="px-3 py-1.5 bg-blue-600 text-white text-xs rounded hover:bg-blue-700 whitespace-nowrap">
                                            Upload
                                        </button>
                                        <button type="button" @click="showUpload = false"
                                                class="px-3 py-1.5 bg-gray-700 text-gray-300 text-xs rounded hover:bg-gray-600">
                                            Cancel
                                        </button>
                                    </form>
                                    <p class="mt-1 text-xs text-gray-500">JPG, PNG, WebP, GIF, AVIF, HEIC &mdash; max 20 MB</p>
                                    @error('background_image')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
                                </div>
                            </div>
                            @endif {{-- !$isInactive --}}
                        </div>
                    </details>

                @else
                    {{-- Read-only view for non-creators --}}
                    @if($project->description)
                        <div class="mb-4">
                            <span class="text-sm font-medium text-gray-500">Description</span>
                            <div class="markdown-body mt-1 text-gray-300">{!! render_body($project->description) !!}</div>
                        </div>
                    @endif

                    <!-- Read more: Status, Created By, Assignees -->
                    <details class="mt-2 group">
                        <summary class="cursor-pointer text-sm text-blue-400 hover:text-blue-300 list-none flex items-center gap-1">
                            <span class="group-open:hidden">Read more</span>
                            <span class="hidden group-open:inline">Show less</span>
                        </summary>
                        <div class="mt-4 space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <span class="text-sm font-medium text-gray-500">Status</span>
                                    <p class="mt-1">
                                        <span class="inline-block px-2 py-1 text-xs rounded
                                            @if($project->status === 'done') bg-green-100 text-green-800
                                            @elseif($project->status === 'archived') bg-gray-100 text-gray-800
                                            @else bg-blue-100 text-blue-800 @endif">
                                            {{ ucfirst($project->status) }}
                                        </span>
                                    </p>
                                </div>
                                <div>
                                    <span class="text-sm font-medium text-gray-500">Created By</span>
                                    <p class="mt-1 text-gray-300">{{ $project->creator->name }}</p>
                                </div>
                            </div>

                            @if($project->assignees->count() > 0)
                                <div>
                                    <span class="text-sm font-medium text-gray-500">Assigned To</span>
                                    <div class="mt-1 space-y-1">
                                        @foreach($project->assignees as $assignee)
                                            <p class="text-sm text-gray-300">{{ $assignee->name }}</p>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    </details>
                @endif
            </div>
            @endif {{-- end removed details card --}}

            <!-- Project Tasks -->
            <div class="bg-[#202020] border border-gray-700 shadow-sm sm:rounded-lg p-6"
                 x-data="taskFilter"
                 data-projects="{{ json_encode($projects) }}"
                 data-tags="{{ json_encode($tags) }}"
                 data-users="{{ json_encode($users) }}"
                 data-locations="{{ json_encode($locations) }}"
                 data-view-project-id="{{ $project->id }}">
                <div class="flex items-center justify-between mb-4">
                    <button type="button"
                            @click="toggleIncomplete()"
                            title="Toggle task list"
                            class="text-gray-500 hover:text-gray-300 transition-colors flex-shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg"
                             class="h-4 w-4 transition-transform duration-150"
                             :class="showIncomplete ? 'rotate-90' : ''"
                             fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                    <div class="flex items-center gap-3">
                        <label for="sort-results" class="text-sm text-gray-400">Sort:</label>
                        <select id="sort-select" @change="sortBy($event.target.value)"
                                class="text-sm bg-gray-700 border border-gray-600 rounded px-2 py-1 text-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="date" {{ $sort === 'date' ? 'selected' : '' }}>Date & Time</option>
                            <option value="created" {{ $sort === 'created' ? 'selected' : '' }}>Date Added</option>
                            <option value="name" {{ $sort === 'name' ? 'selected' : '' }}>Name</option>
                            <option value="duration" {{ $sort === 'duration' ? 'selected' : '' }}>Duration</option>
                            <option value="location" {{ $sort === 'location' ? 'selected' : '' }}>Location</option>
                            <option value="custom" {{ $sort === 'custom' ? 'selected' : '' }}>Custom Sort</option>
                        </select>
                        @if($sort !== 'custom')
                        <button @click="toggleSortReversed()"
                                title="{{ request()->boolean('reversed') ? 'Reversed — click to restore' : 'Reverse sort order' }}"
                                class="p-1 rounded transition-colors {{ request()->boolean('reversed') ? 'text-blue-400 hover:text-blue-300' : 'text-gray-500 hover:text-gray-300' }}">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7 16V4M3 8l4-4 4 4M17 8v12M13 16l4 4 4-4" />
                            </svg>
                        </button>
                        @endif
                    </div>
                </div>
                <div x-show="showIncomplete">
                    @if(!$isInactive)
                        <x-task-input-bar :project-id="$project->id" filter-placeholder="Filter tasks... (@ tag)" />
                    @else
                        <div class="mb-4">
                            <input type="text"
                                   x-model="query"
                                   x-on:input="filterTasks()"
                                   x-on:keydown.escape="clearFilter()"
                                   placeholder="Filter tasks... (@ tag)"
                                   class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-md text-gray-100 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                    @endif
                    <div x-ref="taskContainer">
                        <x-task-list :tasks="$tasks" :read-only="$isInactive" :sortable="$sort === 'custom'" :reorder-url="route('projects.reorderTasks', $project)" />
                    </div>
                    <div x-show="noResults" x-cloak class="bg-[#202020] p-8 rounded-lg text-center text-gray-400 border border-gray-700">
                        No tasks match your filter.
                    </div>
                </div>

                <x-completed-tasks-section
                    :tasks="$completedTasks"
                    :read-only="$isInactive"
                    :total-count="$completedTasksTotal"
                    :has-more="$completedTasksHasMore"
                    :next-page="2"
                    :ajax-url="$completedTasksHasMore ? route('projects.completedTasks', $project) : null"
                />
                <x-completed-tasks-section
                    :tasks="$archivedTasks"
                    label="Archived tasks"
                    :read-only="true"
                    :show-as-archived="true"
                    :total-count="$archivedTasksTotal"
                    :has-more="$archivedTasksHasMore"
                    :next-page="2"
                    :ajax-url="$archivedTasksHasMore ? route('projects.archivedTasks', $project) : null"
                />
            </div>
        </div>
    </div>

    @if($project->user_id === Auth::id())
    @push('scripts')
    <script nonce="{{ csp_nonce() }}">
        document.addEventListener('alpine:init', () => {
            Alpine.data('projectHeaderControls', () => ({
                showSaveTemplate: false,
                showMenu: false,
                showStatus: false,
                showReminder: false,
                statusTab: 'post',
                openDetails() { this.showMenu = false; this.$dispatch('open-project-details'); },
                openStatusModal() { this.showStatus = true; this.statusTab = 'post'; },
                closeStatusModal() { this.showStatus = false; },
                setStatusTabPost() { this.statusTab = 'post'; },
                setStatusTabHistory() { this.statusTab = 'history'; },
                toggleMenu() { this.showMenu = !this.showMenu; },
                closeMenu() { this.showMenu = false; },
                openSaveTemplate() { this.showMenu = false; this.showSaveTemplate = true; },
                closeSaveTemplate() { this.showSaveTemplate = false; },
                openReminder() { this.showMenu = false; this.showReminder = true; },
                closeReminder() { this.showReminder = false; },
                toggleHeart(projectId) {
                    fetch(`/projects/${projectId}/toggle-heart`, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    })
                    .then(r => r.json())
                    .then(data => {
                        const hearted = data.is_hearted;
                        const btn = document.getElementById('heart-btn');
                        const svg = document.getElementById('heart-svg');
                        btn.dataset.hearted = hearted ? 'true' : 'false';
                        btn.title = hearted ? 'Remove from active projects' : 'Mark as active project';
                        btn.className = `shrink-0 transition-colors ${hearted ? 'text-pink-500' : 'text-gray-600 hover:text-pink-500'}`;
                        svg.setAttribute('fill', hearted ? 'currentColor' : 'none');
                        svg.setAttribute('stroke-width', hearted ? '0' : '1.5');
                    });
                },
            }));

            Alpine.data('projectHeaderEditor', function () {
                return {
                    projectId: 0,
                    name: '',
                    original: '',
                    editing: false,

                    init() {
                        this.projectId = parseInt(this.$el.dataset.projectId);
                        this.name = this.$el.dataset.projectName || '';
                        this.original = this.name;
                    },

                    startEdit() {
                        this.original = this.name;
                        this.editing = true;
                        // See taskPanelEditor.startEdit() in layouts/app.blade.php for why this
                        // waits for a paint, not just a microtask.
                        this.$nextTick(() => requestAnimationFrame(() => {
                            if (this.$refs.nameInput) {
                                this.$refs.nameInput.focus();
                                this.$refs.nameInput.select();
                            }
                        }));
                    },

                    cancel() {
                        this.name = this.original;
                        this.editing = false;
                    },

                    async save() {
                        if (this.name.trim() === this.original.trim()) {
                            this.editing = false;
                            return;
                        }
                        const formData = new FormData();
                        formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);
                        formData.append('field', 'name');
                        formData.append('value', this.name.trim());
                        try {
                            const resp = await fetch(`/projects/${this.projectId}/update-field`, {
                                method: 'POST',
                                body: formData,
                            });
                            const data = await resp.json();
                            if (data.success) {
                                this.original = this.name.trim();
                                this.editing = false;
                            } else {
                                alert(data.message || 'Failed to save');
                                this.name = this.original;
                                this.editing = false;
                            }
                        } catch (e) {
                            alert('An error occurred while saving');
                            this.name = this.original;
                            this.editing = false;
                        }
                    },
                };
            });

            Alpine.data('projectEditor', function () {
                return {
                    projectId: 0,
                    showDetails: false,
                    editing: {},
                    fieldError: null,
                    openDetails() { this.showDetails = true; },
                    closeDetails() { this.showDetails = false; },
                    fields: {
                        name: @js($project->name),
                        description: @js($project->description ?? ''),
                        status: @js($project->status),
                        end_date: @js($project->end_date ? $project->end_date->format('Y-m-d') : ''),
                        auto_close_action: @js($project->auto_close_action ?? 'archived'),
                        assignee_ids: @js($project->assignees->pluck('id')->toArray()),
                    },
                    original: {},

                    init() {
                        this.projectId = parseInt(this.$el.dataset.projectId) || 0;
                        this.original = JSON.parse(JSON.stringify(this.fields));
                    },

                    startEdit(field) {
                        this.editing[field] = true;
                        // See taskPanelEditor.startEdit() in layouts/app.blade.php for why this
                        // waits for a paint, not just a microtask.
                        if (field === 'name') {
                            this.$nextTick(() => requestAnimationFrame(() => {
                                const input = this.$el.querySelector('input[x-model="fields.name"]');
                                if (input) { input.focus(); input.select(); }
                            }));
                        }
                        if (field === 'description') {
                            this.$nextTick(() => requestAnimationFrame(() => {
                                const ta = this.$el.querySelector('textarea[x-model="fields.description"]');
                                if (ta) ta.focus();
                            }));
                        }
                    },

                    cancelEdit(field) {
                        this.editing[field] = false;
                        this.fields[field] = JSON.parse(JSON.stringify(this.original[field]));
                    },

                    async saveField(field) {
                        this.fieldError = null;
                        try {
                            const formData = new FormData();
                            formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);
                            formData.append('field', field);

                            if (Array.isArray(this.fields[field])) {
                                this.fields[field].forEach(value => {
                                    formData.append(field + '[]', value);
                                });
                            } else {
                                formData.append('value', this.fields[field]);
                            }

                            const response = await fetch(`/projects/${this.projectId}/update-field`, {
                                method: 'POST',
                                body: formData,
                            });

                            const data = await response.json();

                            if (data.success) {
                                this.original[field] = JSON.parse(JSON.stringify(this.fields[field]));
                                this.editing[field] = false;
                                window.location.reload();
                            } else {
                                this.fieldError = data.message || 'Failed to update';
                            }
                        } catch (error) {
                            console.error('Error:', error);
                            this.fieldError = 'An error occurred while saving. Check the server logs.';
                        }
                    },
                };
            });

            Alpine.data('reminderDateInput', function () {
                return {
                    dateText: '',
                    resolvedDate: '',
                    datePreview: '',
                    dateError: '',

                    init() {
                        this.resolvedDate = this.$el.dataset.initialDate || '';
                        if (this.resolvedDate && /^\d{4}-\d{2}-\d{2}$/.test(this.resolvedDate)) {
                            const d = new Date(this.resolvedDate + 'T12:00:00');
                            this.dateText = d.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
                            this.datePreview = this.dateText;
                        }
                    },

                    pickDate(value) {
                        if (!value) return;
                        this.resolvedDate = value;
                        const d = new Date(value + 'T12:00:00');
                        this.dateText = d.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
                        this.datePreview = this.dateText;
                        this.dateError = '';
                    },

                    async previewDate() {
                        const input = this.dateText.trim();
                        if (!input) { this.datePreview = ''; this.dateError = ''; this.resolvedDate = ''; return; }
                        try {
                            const resp = await fetch('{{ route('tasks.parseDate') }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                    'Accept': 'application/json',
                                },
                                body: JSON.stringify({ input }),
                            });
                            const data = await resp.json();
                            if (data.success) {
                                this.resolvedDate = data.date;
                                this.datePreview = data.formatted;
                                this.dateError = '';
                            } else {
                                this.resolvedDate = '';
                                this.datePreview = '';
                                this.dateError = 'Could not parse this date';
                            }
                        } catch (e) {
                            this.resolvedDate = '';
                            this.datePreview = '';
                            this.dateError = '';
                        }
                    },
                };
            });
        });
    </script>
    @endpush
    @endif

<script nonce="{{ csp_nonce() }}">
function setDefaultProject(projectId) {
    fetch(`/projects/${projectId}/set-default`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
        },
    }).then(r => r.json()).then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert(data.message || 'Could not set default project.');
        }
    });
}
</script>
</x-app-layout>
