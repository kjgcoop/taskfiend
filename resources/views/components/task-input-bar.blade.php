@props(['date' => null, 'filterPlaceholder' => 'Filter tasks... (# project, @ tag)'])

<div class="mb-4">
    {{-- Create mode (default) --}}
    <div x-show="mode === 'create'" class="flex gap-2 items-center">
        <form action="{{ route('tasks.store') }}" method="POST" class="flex-1 flex gap-2">
            @csrf
            <input type="hidden" name="quick_add" value="1">
            @if($date)
                <input type="hidden" name="date" value="{{ $date }}">
            @endif
            <div class="relative flex-1" @click.outside="showAutocomplete = false">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                </span>
                <input type="text"
                       name="name"
                       x-ref="createInput"
                       placeholder="Add a task..."
                       autocomplete="off"
                       x-on:input="handleInput($event)"
                       x-on:keydown="handleKeydown($event)"
                       class="w-full pl-9 pr-4 py-2 bg-gray-700 border border-gray-600 rounded-md text-gray-100 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">

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
                                     @click.prevent="selectAutocomplete(project.name)">
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
                                     @click.prevent="selectAutocomplete(tag.tag_name)">
                                    <span class="text-xs" :style="'color: ' + tag.color">@</span>
                                    <span :style="'color: ' + tag.color" x-text="tag.tag_name"></span>
                                </div>
                            </template>
                            <div x-show="filteredTags.length === 0"
                                 class="px-3 py-2 text-sm text-gray-500 italic">No matching tags</div>
                        </div>
                    </template>
                </div>
            </div>
            <button type="submit"
                    class="px-3 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm font-medium flex-shrink-0">
                Add
            </button>
        </form>
        <button @click="switchToFilter()"
                title="Filter tasks"
                class="flex-shrink-0 p-2 text-gray-500 hover:text-gray-300 rounded-md hover:bg-gray-700 transition">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z" />
            </svg>
        </button>
    </div>

    {{-- Filter mode --}}
    <div x-show="mode === 'filter'" x-cloak class="flex gap-2 items-center">
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
    </div>
</div>
