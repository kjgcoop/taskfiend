<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-100 leading-tight">
                {{ $project->name }}
            </h2>
            <div class="flex gap-2">
                <a href="{{ route('projects.export-template', $project) }}" class="px-4 py-2 bg-gray-700 border border-gray-600 text-gray-100 rounded hover:bg-gray-600">
                    Export as Template
                </a>
                @if($project->user_id === Auth::id() && !$project->is_inbox)
                    <a href="{{ route('projects.edit', $project) }}" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                        Edit
                    </a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <!-- Project Details -->
            <div class="bg-gray-800 border border-gray-700 overflow-hidden shadow-sm sm:rounded-lg p-6">
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

                @if($project->description)
                    <div class="mt-4">
                        <span class="text-sm font-medium text-gray-500">Description</span>
                        <p class="mt-1 text-gray-300">{{ $project->description }}</p>
                    </div>
                @endif

                @if($project->assignees->count() > 0)
                    <div class="mt-4">
                        <span class="text-sm font-medium text-gray-500">Assigned To</span>
                        <div class="mt-1 space-y-1">
                            @foreach($project->assignees as $assignee)
                                <p class="text-sm text-gray-300">{{ $assignee->name }}</p>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <!-- Project Tasks -->
            <div class="bg-gray-800 border border-gray-700 overflow-hidden shadow-sm sm:rounded-lg p-6" x-data="taskFilter()">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-100">Tasks</h3>
                    <a href="{{ route('tasks.create') }}?project_id={{ $project->id }}" class="text-sm text-blue-400 hover:underline">
                        Add Task
                    </a>
                </div>
                <div class="mb-4">
                    <input type="text"
                           x-model="query"
                           @input="filterTasks()"
                           placeholder="Filter tasks... (@ tag)"
                           class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-md text-gray-100 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <div x-ref="taskContainer">
                    <x-task-list :tasks="$tasks" />
                </div>
                <div x-show="noResults" x-cloak class="bg-gray-800 p-8 rounded-lg text-center text-gray-400 border border-gray-700">
                    No tasks match your filter.
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
