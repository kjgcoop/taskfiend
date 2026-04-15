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
            <div x-show="showImportForm" x-cloak class="bg-[#202020] border border-gray-700 p-6 rounded-lg shadow">
                <h3 class="text-lg font-semibold text-gray-100 mb-4">Import Project Template</h3>
                <form action="{{ route('projects.import-template') }}" method="POST" enctype="multipart/form-data" @submit="if(!projectName) { alert('Please enter a project name'); return false; }">
                    @csrf
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Template File</label>
                            <input type="file" name="template_file" accept=".zip" required
                                   @change="templateFile = $event.target.files[0]; if(!projectName && templateFile) { projectName = templateFile.name.replace(/\.zip$/, '').replace(/^taskfiend_template_/, '').replace(/_\d{4}-\d{2}-\d{2}$/, ''); }"
                                   class="block w-full text-sm text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-gray-700 file:text-gray-100 hover:file:bg-gray-600 bg-[#101010] border border-gray-600 rounded-md">
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
                    @php $hasBg = !empty($project->background_image); @endphp
                    <div class="relative border border-gray-700 rounded-lg shadow hover:shadow-md transition cursor-pointer overflow-hidden {{ $hasBg ? 'min-h-[140px]' : 'bg-[#202020]' }}"
                         onclick="window.location='{{ route('projects.show', $project) }}'"
                         @if($hasBg)
                         style="background-image: url('{{ route('projects.background', $project) }}'); background-size: cover; background-position: center;"
                         @endif>
                        @if($hasBg)
                            <div class="absolute inset-0 bg-black/55"></div>
                        @endif
                        <div class="relative p-6">
                            <div class="flex items-start justify-between gap-2">
                                <h3 class="font-semibold text-lg {{ $hasBg ? 'text-white' : 'text-gray-100' }}">{{ $project->name }}</h3>
                                <span x-data="{ copied: false }" class="shrink-0 flex items-center gap-1 mt-0.5">
                                    <span class="text-xs {{ $hasBg ? 'text-gray-300' : 'text-gray-500' }}">#{{ $project->id }}</span>
                                    <button @click.stop="navigator.clipboard.writeText('{{ route('projects.show', $project) }}').then(() => { copied = true; setTimeout(() => copied = false, 2000) })"
                                            title="Copy link"
                                            class="{{ $hasBg ? 'text-gray-300 hover:text-white' : 'text-gray-500 hover:text-gray-300' }} transition-colors">
                                        <span x-show="!copied">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                                            </svg>
                                        </span>
                                        <span x-show="copied" x-cloak class="text-xs text-green-400">Copied!</span>
                                    </button>
                                </span>
                            </div>
                            @if($project->description)
                                <p class="text-sm {{ $hasBg ? 'text-gray-200' : 'text-gray-400' }} mt-2">{{ Str::limit($project->description, 100) }}</p>
                            @endif
                            <div class="flex items-center justify-between mt-4">
                                <span class="text-sm {{ $hasBg ? 'text-gray-300' : 'text-gray-500' }}">
                                    {{ $project->open_tasks_count }} open
                                    @if($project->done_tasks_count > 0)
                                        &middot; {{ $project->done_tasks_count }} completed
                                    @endif
                                </span>
                                <div class="flex items-center gap-2">
                                    @if($project->status !== 'incomplete')
                                    <span class="inline-block px-2 py-1 text-xs rounded
                                        @if($project->status === 'done') bg-green-100 text-green-800
                                        @else bg-blue-100 text-blue-800 @endif">
                                        {{ ucfirst($project->status) }}
                                    </span>
                                    @endif
                                    <a href="{{ route('projects.export-markdown', $project) }}"
                                       onclick="event.stopPropagation()"
                                       class="text-xs {{ $hasBg ? 'text-gray-300 hover:text-white' : 'text-gray-500 hover:text-gray-300' }}">
                                        Export .md
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full bg-[#202020] border border-gray-700 p-8 rounded-lg text-center text-gray-500">
                        No projects yet. Create your first project!
                    </div>
                @endforelse
            </div>

            @if($inactiveProjects->count() > 0)
                <div>
                    <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-widest mb-3">
                        Inactive Projects
                    </h3>
                    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        @foreach($inactiveProjects as $project)
                            <div class="bg-[#161616] border border-gray-700/50 p-6 rounded-lg opacity-60 hover:opacity-80 transition cursor-pointer"
                                 onclick="window.location='{{ route('projects.show', $project) }}'">
                                <div class="flex items-start justify-between gap-2">
                                    <h3 class="font-semibold text-lg text-gray-400 line-through">{{ $project->name }}</h3>
                                    <div class="shrink-0 flex items-center gap-2">
                                        <span x-data="{ copied: false }" class="flex items-center gap-1">
                                            <span class="text-xs text-gray-600">#{{ $project->id }}</span>
                                            <button @click.stop="navigator.clipboard.writeText('{{ route('projects.show', $project) }}').then(() => { copied = true; setTimeout(() => copied = false, 2000) })"
                                                    title="Copy link"
                                                    class="text-gray-600 hover:text-gray-400 transition-colors">
                                                <span x-show="!copied">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                                                    </svg>
                                                </span>
                                                <span x-show="copied" x-cloak class="text-xs text-green-400">Copied!</span>
                                            </button>
                                        </span>
                                        @if($project->status === 'done')
                                            <span class="inline-block px-2 py-1 text-xs rounded bg-green-900/50 text-green-400">Done</span>
                                        @else
                                            <span class="inline-block px-2 py-1 text-xs rounded bg-gray-700 text-gray-400">Archived</span>
                                        @endif
                                    </div>
                                </div>
                                @if($project->description)
                                    <p class="text-sm text-gray-600 mt-2">{{ Str::limit($project->description, 100) }}</p>
                                @endif
                                <div class="flex items-center justify-between mt-4">
                                    <span class="text-sm text-gray-600">
                                        {{ $project->open_tasks_count }} open
                                        @if($project->done_tasks_count > 0)
                                            &middot; {{ $project->done_tasks_count }} completed
                                        @endif
                                    </span>
                                    <a href="{{ route('projects.export-markdown', $project) }}"
                                       onclick="event.stopPropagation()"
                                       class="text-xs text-gray-600 hover:text-gray-400">
                                        Export .md
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
