<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-100 leading-tight">
            @if(isset($task))
                Change Log - {{ $task->name }}
            @elseif(isset($project))
                Change Log - {{ $project->name }}
            @elseif(isset($tag))
                Change Log - {{ $tag->tag_name }}
            @else
                My Change Log
            @endif
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-gray-800 border border-gray-700 overflow-hidden shadow-sm sm:rounded-lg p-6">
                @if(isset($task))
                    <div class="mb-4">
                        <a href="{{ route('tasks.show', $task) }}" class="text-sm text-blue-400 hover:underline">
                            &larr; Back to Task
                        </a>
                    </div>
                    <x-change :changes="$task->changeLogs" />
                @elseif(isset($project))
                    <div class="mb-4">
                        <a href="{{ route('projects.show', $project) }}" class="text-sm text-blue-400 hover:underline">
                            &larr; Back to Project
                        </a>
                    </div>
                    <x-change :changes="$project->changeLogs" />
                @elseif(isset($tag))
                    <div class="mb-4">
                        <a href="{{ route('tags.show', $tag) }}" class="text-sm text-blue-400 hover:underline">
                            &larr; Back to Tag
                        </a>
                    </div>
                    <x-change :changes="$tag->changeLogs" />
                @else
                    <x-change :changes="$changeLogs" />
                @endif

            </div>
        </div>
    </div>
</x-app-layout>
