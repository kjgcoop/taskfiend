<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2">
            <a href="{{ route('projects.show', $project) }}" class="text-gray-400 hover:text-gray-100 transition-colors">
                {{ $project->name }}
            </a>
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
            </svg>
            <h2 class="font-semibold text-xl text-gray-100 leading-tight">Review Import</h2>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 space-y-5">

            @if(empty($toCreate) && empty($toChangeStatus) && $incompleteCount === 0)
                <div class="bg-gray-800 border border-gray-700 rounded-lg p-6 text-center">
                    <p class="text-gray-400">No changes detected. The file matches the current project state.</p>
                    <a href="{{ route('projects.show', $project) }}"
                       class="mt-4 inline-block text-sm text-blue-400 hover:text-blue-300">
                        Back to project
                    </a>
                </div>
            @else

                {{-- Limitations notice --}}
                <div class="bg-gray-800 border border-gray-600 rounded-lg px-4 py-3 text-xs text-gray-400">
                    <span class="font-medium text-gray-300">Note:</span>
                    Task names must match exactly — renamed tasks will be created as new tasks.
                    Subtasks and indented items are not supported and were ignored.
                </div>

                {{-- New tasks --}}
                @if(!empty($toCreate))
                    <div class="bg-gray-800 border border-gray-700 rounded-lg overflow-hidden">
                        <div class="px-4 py-3 border-b border-gray-700 flex items-center gap-2">
                            <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-green-700 text-green-200 text-xs font-bold">+</span>
                            <span class="text-sm font-medium text-gray-200">{{ count($toCreate) }} task{{ count($toCreate) !== 1 ? 's' : '' }} will be created</span>
                        </div>
                        <ul class="divide-y divide-gray-700">
                            @foreach($toCreate as $item)
                                <li class="px-4 py-2.5 flex items-center justify-between gap-3">
                                    <span class="text-sm text-gray-200">{{ $item['name'] }}</span>
                                    <span class="shrink-0 text-xs px-2 py-0.5 rounded-full
                                        @if($item['section'] === 'incomplete') bg-gray-700 text-gray-300
                                        @elseif($item['section'] === 'done') bg-green-900/50 text-green-300
                                        @else bg-gray-700 text-gray-500 @endif">
                                        {{ ucfirst($item['section']) }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- Status changes --}}
                @if(!empty($toChangeStatus))
                    <div class="bg-gray-800 border border-gray-700 rounded-lg overflow-hidden">
                        <div class="px-4 py-3 border-b border-gray-700 flex items-center gap-2">
                            <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-blue-700 text-blue-200 text-xs font-bold">↕</span>
                            <span class="text-sm font-medium text-gray-200">{{ count($toChangeStatus) }} task{{ count($toChangeStatus) !== 1 ? 's' : '' }} will change status</span>
                        </div>
                        <ul class="divide-y divide-gray-700">
                            @foreach($toChangeStatus as $change)
                                <li class="px-4 py-2.5 flex items-center justify-between gap-3">
                                    <span class="text-sm text-gray-200 truncate">{{ $change['name'] }}</span>
                                    <span class="shrink-0 flex items-center gap-1.5 text-xs text-gray-400">
                                        <span class="px-2 py-0.5 rounded-full bg-gray-700">{{ ucfirst($change['from']) }}</span>
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                        </svg>
                                        <span class="px-2 py-0.5 rounded-full
                                            @if($change['to'] === 'incomplete') bg-gray-700 text-gray-300
                                            @elseif($change['to'] === 'done') bg-green-900/50 text-green-300
                                            @else bg-gray-700 text-gray-500 @endif">
                                            {{ ucfirst($change['to']) }}
                                        </span>
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- Sort order note --}}
                @if($incompleteCount > 0)
                    <div class="bg-gray-800 border border-gray-700 rounded-lg px-4 py-3 flex items-start gap-3">
                        <span class="shrink-0 inline-flex items-center justify-center w-5 h-5 rounded-full bg-indigo-700 text-indigo-200 text-xs font-bold mt-0.5">⇅</span>
                        <p class="text-sm text-gray-300">
                            Sort order will be updated for incomplete tasks.
                            The {{ $incompleteCount }} incomplete task{{ $incompleteCount !== 1 ? 's' : '' }} in the file
                            will be sorted in file order; any incomplete tasks not in the file will be pushed to the bottom.
                        </p>
                    </div>
                @endif

                {{-- Actions --}}
                <form method="POST" action="{{ route('projects.import-markdown.apply', $project) }}">
                    @csrf
                    <input type="hidden" name="payload" value="{{ e($payload) }}">
                    <div class="flex items-center gap-3">
                        <button type="submit"
                                class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded transition-colors">
                            Confirm import
                        </button>
                        <a href="{{ route('projects.import-markdown', $project) }}"
                           class="px-4 py-2 text-sm text-gray-400 hover:text-gray-100 transition-colors">
                            Upload a different file
                        </a>
                        <a href="{{ route('projects.show', $project) }}"
                           class="px-4 py-2 text-sm text-gray-400 hover:text-gray-100 transition-colors">
                            Cancel
                        </a>
                    </div>
                </form>
            @endif

        </div>
    </div>
</x-app-layout>
