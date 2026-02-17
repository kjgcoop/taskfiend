<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-100 leading-tight">
                {{ __('Projects') }}
            </h2>
        </div>
    </x-slot>

    <div class="py-12" x-data="{ showImportForm: false, templateFile: null, projectName: '' }">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <!-- Action Buttons -->
            <div class="flex justify-end gap-2">
                <button @click="showImportForm = !showImportForm" class="inline-flex items-center px-4 py-2 bg-gray-700 border border-gray-600 rounded-md font-semibold text-xs text-gray-100 uppercase tracking-widest hover:bg-gray-600">
                    Import Template
                </button>
                <a href="{{ route('projects.create') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700">
                    New Project
                </a>
            </div>
            <!-- Import Template Form -->
            <div x-show="showImportForm" x-cloak class="bg-[#0a1a0a] border border-gray-700 p-6 rounded-lg shadow">
                <h3 class="text-lg font-semibold text-gray-100 mb-4">Import Project Template</h3>
                <form action="{{ route('projects.import-template') }}" method="POST" enctype="multipart/form-data" @submit="if(!projectName) { alert('Please enter a project name'); return false; }">
                    @csrf
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Template File</label>
                            <input type="file" name="template_file" accept=".zip" required
                                   @change="templateFile = $event.target.files[0]; if(!projectName && templateFile) { projectName = templateFile.name.replace(/\.zip$/, '').replace(/^taskfiend_template_/, '').replace(/_\d{4}-\d{2}-\d{2}$/, ''); }"
                                   class="block w-full text-sm text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-gray-700 file:text-gray-100 hover:file:bg-gray-600 bg-[#050f05] border border-gray-600 rounded-md">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Project Name</label>
                            <input type="text" name="project_name" x-model="projectName" required maxlength="255"
                                   class="w-full bg-gray-700 border border-gray-600 text-gray-100 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 placeholder-gray-500"
                                   placeholder="Enter project name">
                        </div>
                        <div class="flex gap-2">
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                                Import
                            </button>
                            <button type="button" @click="showImportForm = false; templateFile = null; projectName = ''" class="px-4 py-2 bg-gray-700 border border-gray-600 text-gray-100 rounded hover:bg-gray-600">
                                Cancel
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                @forelse($projects as $project)
                    <div class="bg-[#0a1a0a] border border-gray-700 p-6 rounded-lg shadow hover:shadow-md transition cursor-pointer"
                         onclick="window.location='{{ route('projects.show', $project) }}'">
                        <h3 class="font-semibold text-lg text-gray-100">{{ $project->name }}</h3>
                        @if($project->description)
                            <p class="text-sm text-gray-400 mt-2">{{ Str::limit($project->description, 100) }}</p>
                        @endif
                        <div class="flex items-center justify-between mt-4">
                            <span class="text-sm text-gray-500">{{ $project->tasks_count }} tasks</span>
                            <span class="inline-block px-2 py-1 text-xs rounded
                                @if($project->status === 'done') bg-green-100 text-green-800
                                @elseif($project->status === 'archived') bg-gray-100 text-gray-800
                                @else bg-blue-100 text-blue-800 @endif">
                                {{ ucfirst($project->status) }}
                            </span>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full bg-[#0a1a0a] border border-gray-700 p-8 rounded-lg text-center text-gray-500">
                        No projects yet. Create your first project!
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
