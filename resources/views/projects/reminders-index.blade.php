<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-100 leading-tight">
                Project Reminders
            </h2>
            <a href="{{ route('projects.index') }}" class="text-sm text-gray-400 hover:text-gray-200">
                &larr; Back to Projects
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">

            @if($reminders->isEmpty())
                <div class="bg-[#202020] border border-gray-700 rounded-lg p-8 text-center text-gray-500">
                    No active reminders. Open a project to set one.
                </div>
            @else
                <div class="bg-[#202020] border border-gray-700 rounded-lg divide-y divide-gray-700">
                    @foreach($reminders as $reminder)
                        <div class="flex items-center gap-4 px-5 py-4">
                            <div class="flex-1 min-w-0">
                                <a href="{{ route('projects.show', $reminder->project) }}"
                                   class="text-gray-100 font-medium hover:text-white truncate block">
                                    {{ $reminder->project->name }}
                                </a>
                                @if($reminder->recurrence_pattern)
                                    <div class="text-xs text-gray-500 mt-0.5">{{ $reminder->recurrence_pattern }}</div>
                                @endif
                            </div>
                            <div class="text-sm text-gray-300 whitespace-nowrap">
                                {{ $reminder->date->format('l, M j, Y') }}
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
