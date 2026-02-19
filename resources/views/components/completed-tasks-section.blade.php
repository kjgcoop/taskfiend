@props([
    'tasks',
    'label' => 'Show completed tasks',
    'hideDate' => false,
])

@if($tasks->count() > 0)
<div class="mt-4 border-t border-gray-700 pt-4" x-data="{ showCompleted: false }">
    <label class="inline-flex items-center gap-2 cursor-pointer select-none">
        <input type="checkbox"
               x-model="showCompleted"
               class="rounded border-gray-600 bg-gray-700 text-blue-600 focus:ring-blue-500 focus:ring-offset-0">
        <span class="text-sm text-gray-400" :class="showCompleted ? 'text-gray-200' : 'hover:text-gray-200'">
            {{ $label }}
            <span class="text-gray-600">({{ $tasks->count() }})</span>
        </span>
    </label>

    <div x-show="showCompleted" x-cloak class="mt-4">
        <x-task-list :tasks="$tasks" :hide-date="$hideDate" />
    </div>
</div>
@endif
