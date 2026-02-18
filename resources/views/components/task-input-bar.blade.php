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
            <div class="relative flex-1">
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
                       class="w-full pl-9 pr-4 py-2 bg-gray-700 border border-gray-600 rounded-md text-gray-100 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
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
