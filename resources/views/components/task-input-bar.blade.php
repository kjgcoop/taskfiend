@props(['date' => null, 'projectId' => null, 'tagId' => null, 'filterPlaceholder' => 'Filter tasks... (# project, @ tag)'])

<div class="mb-4">
    {{-- Create mode (default, hidden in bulk mode) --}}
    <div x-show="mode === 'create' && !$store.bulkEdit.active" class="flex gap-2 items-center">
        <form action="{{ route('tasks.store') }}" method="POST" class="flex-1 flex flex-col gap-1" @submit.prevent="validateAndSubmit($event)">
            @csrf
            <input type="hidden" name="quick_add" value="1">
            @if($date)
                <input type="hidden" name="date" value="{{ $date }}">
            @endif
            @if($projectId)
                <input type="hidden" name="project_id" value="{{ $projectId }}">
            @endif
            @if($tagId)
                <input type="hidden" name="tag_ids[]" value="{{ $tagId }}">
            @endif
            <div class="flex gap-2 items-center">
                <div class="relative flex-1" @click.outside="showAutocomplete = false">
                    <span class="absolute left-3 top-2.5 text-gray-500 pointer-events-none">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                    </span>
                    <textarea name="name"
                              x-ref="createInput"
                              placeholder="Add a task… (Shift+Enter for multiple)"
                              autocomplete="off"
                              rows="1"
                              x-on:input="handleInput($event)"
                              x-on:keydown="handleKeydown($event)"
                              x-on:click="schedulePreview($event.target.value, $event.target.selectionStart)"
                              x-on:paste="const raw = ($event.clipboardData || window.clipboardData).getData('text'); const cleaned = raw.split('\n').map(l => l.replace(/^[-*] /, '')).join('\n'); if (cleaned !== raw) { $event.preventDefault(); const el = $refs.createInput; const s = el.selectionStart, e = el.selectionEnd; el.value = el.value.slice(0, s) + cleaned + el.value.slice(e); el.selectionStart = el.selectionEnd = s + cleaned.length; el.dispatchEvent(new Event('input')); } $nextTick(() => { $refs.createInput.style.height = 'auto'; $refs.createInput.style.height = Math.min($refs.createInput.scrollHeight, 200) + 'px'; })"
                              :class="nameError ? 'border-red-500 focus:ring-red-500' : 'border-gray-600 focus:ring-blue-500'"
                              style="resize: none; overflow: hidden;"
                              class="w-full pl-9 pr-4 py-2 bg-gray-700 border rounded-md text-gray-100 placeholder-gray-500 focus:outline-none focus:ring-2 focus:border-transparent">{{ old('name') }}</textarea>

                    {{-- Autocomplete dropdown --}}
                    <div x-show="showAutocomplete"
                         x-transition:enter="transition ease-out duration-100"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         x-cloak
                         class="absolute z-20 w-full top-full left-0 mt-1 bg-gray-800 border border-gray-600 rounded-md shadow-lg max-h-52 overflow-auto">

                        <template x-if="autocompleteType === 'project'">
                            <div>
                                <div class="px-3 py-1.5 text-xs font-semibold text-gray-400 border-b border-gray-700">Projects</div>
                                <template x-for="(project, i) in filteredProjects" :key="project.id">
                                    <div class="px-3 py-2 cursor-pointer text-sm text-gray-300 flex items-center gap-2"
                                         :class="{ 'bg-gray-600 text-gray-100': autocompleteIndex === i }"
                                         @mouseenter="autocompleteIndex = i"
                                         @click.prevent="selectAutocomplete(project.name, project.id)">
                                        <span class="text-gray-500 text-xs">#</span>
                                        <span x-text="project.name"></span>
                                    </div>
                                </template>
                                <div x-show="filteredProjects.length === 0"
                                     class="px-3 py-2 text-sm text-gray-500 italic">No matching projects</div>
                            </div>
                        </template>

                        <template x-if="autocompleteType === 'tag'">
                            <div>
                                <div class="px-3 py-1.5 text-xs font-semibold text-gray-400 border-b border-gray-700">Tags</div>
                                <template x-for="(tag, i) in filteredTags" :key="tag.id">
                                    <div class="px-3 py-2 cursor-pointer text-sm flex items-center gap-2"
                                         :class="{ 'bg-gray-600': autocompleteIndex === i }"
                                         @mouseenter="autocompleteIndex = i"
                                         @click.prevent="selectAutocomplete(tag.tag_name, tag.id)">
                                        <span class="text-xs" :style="'color: ' + tag.color">@</span>
                                        <span :style="'color: ' + tag.color" x-text="tag.tag_name"></span>
                                    </div>
                                </template>
                                <div x-show="filteredTags.length === 0"
                                     class="px-3 py-2 text-sm text-gray-500 italic">No matching tags</div>
                            </div>
                        </template>

                        <template x-if="autocompleteType === 'location'">
                            <div>
                                <div class="px-3 py-1.5 text-xs font-semibold text-gray-400 border-b border-gray-700">Locations</div>
                                <template x-for="(loc, i) in filteredLocations" :key="loc">
                                    <div class="px-3 py-2 cursor-pointer text-sm text-gray-300 flex items-center gap-2"
                                         :class="{ 'bg-gray-600 text-gray-100': autocompleteIndex === i }"
                                         @mouseenter="autocompleteIndex = i"
                                         @click.prevent="selectAutocomplete(loc)">
                                        <span class="text-orange-400 text-xs">+</span>
                                        <span x-text="loc"></span>
                                    </div>
                                </template>
                                <div x-show="filteredLocations.length === 0"
                                     class="px-3 py-2 text-sm text-gray-500 italic">No prior locations — any text after + works</div>
                            </div>
                        </template>

                        <template x-if="autocompleteType === 'user'">
                            <div>
                                <div class="px-3 py-1.5 text-xs font-semibold text-gray-400 border-b border-gray-700">Assignees</div>
                                <template x-for="(user, i) in filteredUsers" :key="user.id">
                                    <div class="px-3 py-2 cursor-pointer text-sm text-gray-300 flex items-center gap-2"
                                         :class="{ 'bg-gray-600 text-gray-100': autocompleteIndex === i }"
                                         @mouseenter="autocompleteIndex = i"
                                         @click.prevent="selectAutocomplete(user.name, user.id)">
                                        <span class="text-cyan-400 text-xs">&amp;</span>
                                        <span x-text="user.name"></span>
                                    </div>
                                </template>
                                <div x-show="filteredUsers.length === 0"
                                     class="px-3 py-2 text-sm text-gray-500 italic">No matching users</div>
                            </div>
                        </template>
                    </div>
                </div>
                <button type="submit"
                        :disabled="submitting"
                        :class="submitting ? 'opacity-60 cursor-wait' : 'hover:bg-blue-700'"
                        class="px-3 py-2 bg-blue-600 text-white rounded-md text-sm font-medium flex-shrink-0">
                    <span x-show="!submitting">Add</span>
                    <span x-show="submitting" x-cloak>…</span>
                </button>
            </div>
            {{-- Multi-line task count badge --}}
            <div x-show="lineCount > 1" x-cloak class="pl-9">
                <span class="text-xs text-blue-400" x-text="lineCount + ' tasks will be created'"></span>
            </div>
            {{-- Client-side validation error --}}
            <div x-show="nameError" x-cloak class="pl-1">
                <p x-text="nameError" class="text-xs text-red-400"></p>
            </div>
            {{-- Server-side error (AJAX) --}}
            <div x-show="serverError" x-cloak class="pl-1">
                <p x-text="serverError" class="text-xs text-red-400 whitespace-pre-line"></p>
            </div>
            @error('name')
                <p class="text-xs text-red-400 pl-1">{{ $message }}</p>
            @enderror

            {{-- Live parse preview: appears ~400ms after typing stops when special terms are detected --}}
            <div x-show="preview && preview.has_special"
                 x-cloak
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 class="pl-9 flex flex-wrap items-baseline gap-x-1.5 gap-y-0.5 text-xs">
                <span class="text-gray-300 break-words min-w-0" x-text="'\u201c' + (preview && preview.title || '') + '\u201d'"></span>
                <template x-if="preview && preview.nodate">
                    <span class="contents">
                        <span class="text-gray-600" aria-hidden="true">·</span>
                        <span class="text-gray-500 whitespace-nowrap italic">no date</span>
                    </span>
                </template>
                <template x-if="preview && preview.date && !preview.nodate">
                    <span class="contents">
                        <span class="text-gray-600" aria-hidden="true">·</span>
                        <span class="text-blue-400 whitespace-nowrap" x-text="preview.date"></span>
                    </span>
                </template>
                <template x-if="preview && preview.recurrence">
                    <span class="contents">
                        <span class="text-gray-600" aria-hidden="true">·</span>
                        <span class="text-purple-400 whitespace-nowrap" x-text="'repeats: ' + preview.recurrence"></span>
                    </span>
                </template>
                <template x-if="preview && preview.project">
                    <span class="contents">
                        <span class="text-gray-600" aria-hidden="true">·</span>
                        <span class="text-green-400 whitespace-nowrap" x-text="'#' + preview.project"></span>
                    </span>
                </template>
                <template x-if="preview && preview.tags_display">
                    <span class="contents">
                        <span class="text-gray-600" aria-hidden="true">·</span>
                        <span class="text-yellow-400 whitespace-nowrap" x-text="preview.tags_display"></span>
                    </span>
                </template>
                <template x-if="preview && preview.location">
                    <span class="contents">
                        <span class="text-gray-600" aria-hidden="true">·</span>
                        <span class="text-orange-400 whitespace-nowrap"
                              x-text="(preview.show_map ? '++ ' : '+ ') + preview.location"></span>
                    </span>
                </template>
                <template x-if="preview && preview.assignees_display">
                    <span class="contents">
                        <span class="text-gray-600" aria-hidden="true">·</span>
                        <span class="text-cyan-400 whitespace-nowrap" x-text="preview.assignees_display"></span>
                    </span>
                </template>
                <template x-if="preview && preview.unknown_assignees">
                    <span class="contents">
                        <span class="text-gray-600" aria-hidden="true">·</span>
                        <span class="text-amber-400 whitespace-nowrap" x-text="preview.unknown_assignees + ' — not recognized as a user, kept as text'"></span>
                    </span>
                </template>
            </div>
        </form>
        <button @click="switchToFilter()"
                title="Filter tasks"
                class="flex-shrink-0 p-2 text-gray-500 hover:text-gray-300 rounded-md hover:bg-gray-700 transition">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z" />
            </svg>
        </button>
        <button @click="$store.bulkEdit.toggle()"
                title="Bulk edit mode"
                :class="$store.bulkEdit.active ? 'text-blue-400 bg-gray-700' : 'text-gray-500 hover:text-gray-300'"
                class="flex-shrink-0 p-2 rounded-md hover:bg-gray-700 transition">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
            </svg>
        </button>
    </div>

    {{-- Filter mode (hidden in bulk mode) --}}
    <div x-show="mode === 'filter' && !$store.bulkEdit.active" x-cloak class="flex gap-2 items-center">
        <button @click="switchToCreate()"
                title="Back to add task"
                class="flex-shrink-0 p-2 text-gray-500 hover:text-gray-300 rounded-md hover:bg-gray-700 transition">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
            </svg>
        </button>
        <input type="text"
               x-ref="filterInput"
               x-model="query"
               x-on:input="filterTasks()"
               x-on:keydown.escape="switchToCreate()"
               placeholder="{{ $filterPlaceholder }}"
               class="flex-1 px-4 py-2 bg-gray-700 border border-gray-600 rounded-md text-gray-100 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
        <button @click="$store.bulkEdit.toggle()"
                title="Bulk edit mode"
                :class="$store.bulkEdit.active ? 'text-blue-400 bg-gray-700' : 'text-gray-500 hover:text-gray-300'"
                class="flex-shrink-0 p-2 rounded-md hover:bg-gray-700 transition">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
            </svg>
        </button>
    </div>

    {{-- Bulk edit mode header --}}
    <div x-show="$store.bulkEdit.active" x-cloak class="flex gap-3 items-center">
        <button @click="$store.bulkEdit.toggle()"
                title="Exit bulk edit"
                class="flex-shrink-0 p-2 text-blue-400 bg-gray-700 rounded-md hover:bg-gray-600 transition">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
            </svg>
        </button>
        <span class="text-sm font-medium text-blue-400">Bulk edit</span>
        <span class="text-sm text-gray-400">
            <span x-text="$store.bulkEdit.count"></span>
            <span x-text="$store.bulkEdit.count === 1 ? 'task' : 'tasks'"></span>
            selected
        </span>
        <button @click="$store.bulkEdit.selectAllVisible()"
                class="text-xs text-blue-400 hover:text-blue-300 underline">
            Select all visible
        </button>
        <button @click="$store.bulkEdit.deselectAll()"
                x-show="$store.bulkEdit.count > 0"
                class="text-xs text-gray-500 hover:text-gray-300 underline">
            Deselect all
        </button>
    </div>
</div>
