<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2">
            <a href="{{ route('projects.show', $project) }}" class="text-gray-400 hover:text-gray-100 transition-colors">
                {{ $project->name }}
            </a>
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
            </svg>
            <h2 class="font-semibold text-xl text-gray-100 leading-tight">Import from .md</h2>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">

            @if(!empty($errors))
                <div class="mb-6 bg-red-900/40 border border-red-700 rounded-lg p-4">
                    <p class="text-sm font-semibold text-red-300 mb-2">The file contains unrecognized headings. Please fix the following and re-upload:</p>
                    <ul class="list-disc list-inside space-y-1">
                        @foreach($errors as $error)
                            <li class="text-sm text-red-400">{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-gray-800 border border-gray-700 rounded-lg p-6">
                <p class="text-sm text-gray-300 mb-5">
                    Upload a markdown file to bulk-update tasks in <span class="text-gray-100 font-medium">{{ $project->name }}</span>.
                    New tasks will be created; existing tasks may have their status or sort order updated.
                    Tasks not present in the file are left unchanged.
                </p>

                <form method="POST" action="{{ route('projects.import-markdown.preview', $project) }}" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-5">
                        <label for="markdown_file" class="block text-sm font-medium text-gray-300 mb-2">Markdown file (.md or .txt)</label>
                        <input type="file"
                               id="markdown_file"
                               name="markdown_file"
                               accept=".md,.txt"
                               required
                               class="block w-full text-sm text-gray-300
                                      file:mr-4 file:py-2 file:px-4
                                      file:rounded file:border-0
                                      file:text-sm file:font-medium
                                      file:bg-gray-700 file:text-gray-200
                                      hover:file:bg-gray-600
                                      cursor-pointer">
                    </div>

                    <div class="border-t border-gray-700 pt-4 mt-4 text-xs text-gray-500 space-y-1 mb-5">
                        <p class="font-medium text-gray-400">Expected format:</p>
                        <p>Use <code class="bg-gray-700 px-1 rounded"># Incomplete</code>, <code class="bg-gray-700 px-1 rounded"># Done</code>, and <code class="bg-gray-700 px-1 rounded"># Archived</code> headings.</p>
                        <p>List tasks with <code class="bg-gray-700 px-1 rounded">- Task name</code> or <code class="bg-gray-700 px-1 rounded">* Task name</code>.</p>
                        <p>Task names must match exactly to be recognized as existing tasks. Renamed tasks are treated as new tasks.</p>
                        <p>Subtasks and indented items are ignored.</p>
                    </div>

                    <div class="flex items-center gap-3">
                        <button type="submit"
                                class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded transition-colors">
                            Preview changes
                        </button>
                        <a href="{{ route('projects.show', $project) }}"
                           class="px-4 py-2 text-sm text-gray-400 hover:text-gray-100 transition-colors">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>

        </div>
    </div>
</x-app-layout>
