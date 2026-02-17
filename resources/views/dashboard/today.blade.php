<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div class="flex items-center gap-3">
                <h2 class="font-semibold text-xl text-gray-100 leading-tight">
                    {{ __('Today') }} - {{ now()->format('l, F j, Y') }}
                </h2>
                <a href="{{ route('day') }}?date={{ now()->addDay()->format('Y-m-d') }}" class="text-gray-400 hover:text-gray-100" title="Tomorrow">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                </a>
            </div>
            <a href="{{ route('tasks.create') }}?date={{ now()->format('Y-m-d') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700">
                Add Task
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <x-task-list :tasks="$tasks" />
        </div>
    </div>
</x-app-layout>
