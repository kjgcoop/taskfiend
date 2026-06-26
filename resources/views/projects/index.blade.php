<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-100 leading-tight">
                {{ __('Projects') }}
            </h2>
        </div>
    </x-slot>

    <div class="py-12" x-data="projectsIndex">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <!-- Action Buttons -->
            <div class="flex justify-between items-center gap-2">
                <label class="flex items-center gap-2 text-sm text-gray-400 cursor-pointer select-none">
                    <input type="checkbox" x-model="favoritesOnly" class="rounded bg-gray-700 border-gray-600 text-pink-500 focus:ring-pink-500">
                    Favorites only
                </label>
                <div class="flex gap-2">
                @if($scheduledCount > 0)
                <a href="{{ route('scheduled-projects.index') }}" class="inline-flex items-center gap-1.5 px-4 py-2 bg-gray-700 border border-gray-600 rounded-md font-semibold text-xs text-gray-100 uppercase tracking-widest hover:bg-gray-600">
                    <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-blue-600 text-white text-[10px] font-bold">{{ $scheduledCount }}</span>
                    Scheduled
                </a>
                @endif
                <a href="{{ route('templates.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-700 border border-gray-600 rounded-md font-semibold text-xs text-gray-100 uppercase tracking-widest hover:bg-gray-600">
                    From Template
                </a>
                <button @click="toggleImportTemplate()" class="inline-flex items-center px-4 py-2 bg-gray-700 border border-gray-600 rounded-md font-semibold text-xs text-gray-100 uppercase tracking-widest hover:bg-gray-600">
                    Import Template
                </button>
                <button @click="toggleMarkdownImport()" class="inline-flex items-center px-4 py-2 bg-gray-700 border border-gray-600 rounded-md font-semibold text-xs text-gray-100 uppercase tracking-widest hover:bg-gray-600">
                    Import from Markdown
                </button>
                <a href="{{ route('projects.create') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700">
                    New Project
                </a>
                </div>
            </div>
            <!-- Import from Markdown Form -->
            <div x-show="showMarkdownImportForm" x-cloak class="bg-[#202020] border border-gray-700 p-6 rounded-lg shadow">
                <h3 class="text-lg font-semibold text-gray-100 mb-4">Create Project from Markdown</h3>
                @if(session('markdown_errors'))
                    <div class="mb-4 bg-red-900/40 border border-red-700 rounded-lg p-4">
                        <p class="text-sm font-semibold text-red-300 mb-2">The file contains unrecognized headings. Please fix the following and re-upload:</p>
                        <ul class="list-disc list-inside space-y-1">
                            @foreach(session('markdown_errors') as $err)
                                <li class="text-sm text-red-400">{{ $err }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                <form action="{{ route('projects.create-from-markdown') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Project Name</label>
                            <input type="text" name="project_name" required maxlength="255"
                                   value="{{ old('project_name') }}"
                                   class="w-full bg-gray-700 border border-gray-600 text-gray-100 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 placeholder-gray-500"
                                   placeholder="Enter project name">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Markdown File</label>
                            <input type="file" name="markdown_file" accept=".md,.txt" required
                                   class="block w-full text-sm text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-gray-700 file:text-gray-100 hover:file:bg-gray-600 bg-[#101010] border border-gray-600 rounded-md">
                            <p class="mt-1 text-xs text-gray-500">Use <code class="bg-gray-700 px-1 rounded"># Incomplete</code>, <code class="bg-gray-700 px-1 rounded"># Done</code>, <code class="bg-gray-700 px-1 rounded"># Archived</code> headings with <code class="bg-gray-700 px-1 rounded">- Task name</code> items.</p>
                        </div>
                        <div class="flex gap-2">
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                                Create Project
                            </button>
                            <button type="button" @click="showMarkdownImportForm = false" class="px-4 py-2 bg-gray-700 border border-gray-600 text-gray-100 rounded hover:bg-gray-600">
                                Cancel
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Import Template Form -->
            <div x-show="showImportForm" x-cloak class="bg-[#202020] border border-gray-700 p-6 rounded-lg shadow">
                <h3 class="text-lg font-semibold text-gray-100 mb-4">Import Project Template</h3>
                <form action="{{ route('projects.import-template') }}" method="POST" enctype="multipart/form-data" @submit.prevent="submitImport($event)">
                    @csrf
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Template File</label>
                            <input type="file" name="template_file" accept=".zip" required
                                   @change="handleFileChange($event)"
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
                            <button type="button" @click="cancelImport()" class="px-4 py-2 bg-gray-700 border border-gray-600 text-gray-100 rounded hover:bg-gray-600">
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
                         x-data="clickableCard" data-href="{{ route('projects.show', $project) }}"
                         x-show="!favoritesOnly || {{ $project->is_hearted ? 'true' : 'false' }}"
                         @click="go($event)"
                         @if($hasBg)
                         style="background-image: url('{{ route('projects.background', $project) }}'); background-size: cover; background-position: center;"
                         @endif>
                        @if($hasBg)
                            <div class="absolute inset-0 bg-black/55"></div>
                        @endif
                        <div class="relative p-6">
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex items-center gap-1.5 min-w-0">
                                    @if($project->user_id === Auth::id())
                                        <button @click.stop="toggleHeartProject({{ $project->id }}, $el)"
                                                title="{{ $project->is_hearted ? 'Remove from active projects' : 'Mark as active project' }}"
                                                data-hearted="{{ $project->is_hearted ? 'true' : 'false' }}"
                                                data-has-bg="{{ $hasBg ? 'true' : 'false' }}"
                                                class="shrink-0 transition-colors {{ $project->is_hearted ? ($hasBg ? 'text-pink-400' : 'text-pink-500') : ($hasBg ? 'text-gray-400 hover:text-pink-400' : 'text-gray-600 hover:text-pink-500') }}">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="{{ $project->is_hearted ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="{{ $project->is_hearted ? '0' : '1.5' }}">
                                                <path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd" />
                                            </svg>
                                        </button>
                                    @endif
                                    @if($project->is_default)
                                        <span title="Default project" class="{{ $hasBg ? 'text-yellow-300' : 'text-yellow-400' }} shrink-0">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                            </svg>
                                        </span>
                                    @elseif($project->user_id === Auth::id())
                                        <button @click.stop="setDefaultProject({{ $project->id }}, $el)"
                                                title="Set as default project"
                                                class="{{ $hasBg ? 'text-gray-400 hover:text-yellow-300' : 'text-gray-600 hover:text-yellow-400' }} shrink-0 transition-colors">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 20 20" stroke="currentColor" stroke-width="1.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                            </svg>
                                        </button>
                                    @endif
                                    <h3 class="font-semibold text-lg truncate {{ $hasBg ? 'text-white' : 'text-gray-100' }}"
                                        title="{{ $project->description ? Str::limit($project->description, 200) : '' }}">{{ $project->name }}</h3>
                                </div>
                                <span x-data="copyButton" class="shrink-0 flex items-center gap-1 mt-0.5">
                                    <span class="text-xs {{ $hasBg ? 'text-gray-300' : 'text-gray-500' }}">#{{ $project->id }}</span>
                                    <button @click.stop="copy('{{ route('projects.show', $project) }}')"
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
                            @if($project->latestStatusLog->first())
                                <p class="text-sm {{ $hasBg ? 'text-gray-200' : 'text-gray-400' }} mt-2">{{ Str::limit(strip_tags($project->latestStatusLog->first()->body), 100) }}</p>
                            @endif
                            <div class="flex items-center justify-between mt-4">
                                <span class="text-sm {{ $hasBg ? 'text-gray-300' : 'text-gray-500' }}">
                                    {{ $project->open_tasks_count }} open
                                    @if($project->done_tasks_count > 0)
                                        &middot; {{ $project->done_tasks_count }} completed
                                    @endif
                                </span>
                                <div class="flex items-center gap-2">
                                    @if($project->end_date)
                                        @php
                                            $daysUntil = (int) now()->startOfDay()->diffInDays($project->end_date, false);
                                            if ($daysUntil === 0) {
                                                $textColor = $hasBg ? 'text-orange-300' : 'text-orange-400';
                                                $archiveLabel = 'archives tonight';
                                            } elseif ($daysUntil <= 7) {
                                                $textColor = $hasBg ? 'text-orange-300' : 'text-orange-500';
                                                $archiveLabel = 'archives in ' . $daysUntil . ' ' . Str::plural('day', $daysUntil);
                                            } else {
                                                $textColor = $hasBg ? 'text-gray-300' : 'text-gray-500';
                                                $archiveLabel = 'archives ' . $project->end_date->format('M j');
                                            }
                                        @endphp
                                        <span class="text-sm {{ $textColor }}">{{ $archiveLabel }}</span>
                                    @endif
                                    @if($project->status !== 'incomplete')
                                    <span class="inline-block px-2 py-1 text-xs rounded
                                        @if($project->status === 'done') bg-green-100 text-green-800
                                        @else bg-blue-100 text-blue-800 @endif">
                                        {{ ucfirst($project->status) }}
                                    </span>
                                    @endif
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
                                 x-data="clickableCard" data-href="{{ route('projects.show', $project) }}" @click="go($event)">
                                <div class="flex items-start justify-between gap-2">
                                    <h3 class="font-semibold text-lg text-gray-400 line-through">{{ $project->name }}</h3>
                                    <div class="shrink-0 flex items-center gap-2">
                                        <span x-data="copyButton" class="flex items-center gap-1">
                                            <span class="text-xs text-gray-600">#{{ $project->id }}</span>
                                            <button @click.stop="copy('{{ route('projects.show', $project) }}')"
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
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
<script nonce="{{ csp_nonce() }}">
document.addEventListener('alpine:init', () => {
    Alpine.data('projectsIndex', () => ({
        showImportForm: false,
        showMarkdownImportForm: {{ session('show_markdown_form') ? 'true' : 'false' }},
        templateFile: null,
        projectName: '',
        favoritesOnly: false,
        handleFileChange(event) {
            this.templateFile = event.target.files[0];
            if (!this.projectName && this.templateFile) {
                this.projectName = this.templateFile.name
                    .replace(/\.zip$/, '')
                    .replace(/^taskfiend_template_/, '')
                    .replace(/_\d{4}-\d{2}-\d{2}$/, '');
            }
        },
        toggleImportTemplate() { this.showImportForm = !this.showImportForm; this.showMarkdownImportForm = false; },
        toggleMarkdownImport() { this.showMarkdownImportForm = !this.showMarkdownImportForm; this.showImportForm = false; },
        cancelImport() { this.showImportForm = false; this.templateFile = null; this.projectName = ''; },
        submitImport(event) {
            if (!this.projectName) { alert('Please enter a project name'); return; }
            event.target.submit();
        },
        toggleHeartProject(projectId, btn) {
            fetch(`/projects/${projectId}/toggle-heart`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
            }).then(r => r.json()).then(data => {
                if (data.success) {
                    const hearted = data.is_hearted;
                    const hasBg = btn.dataset.hasBg === 'true';
                    btn.dataset.hearted = hearted ? 'true' : 'false';
                    btn.title = hearted ? 'Remove from active projects' : 'Mark as active project';
                    const svg = btn.querySelector('svg');
                    svg.setAttribute('fill', hearted ? 'currentColor' : 'none');
                    svg.setAttribute('stroke-width', hearted ? '0' : '1.5');
                    if (hearted) {
                        btn.className = btn.className.replace(/text-gray-\d+ hover:text-pink-\d+/, hasBg ? 'text-pink-400' : 'text-pink-500');
                    } else {
                        const activeClass = hasBg ? 'text-pink-400' : 'text-pink-500';
                        const inactiveClass = hasBg ? 'text-gray-400 hover:text-pink-400' : 'text-gray-600 hover:text-pink-500';
                        btn.className = btn.className.replace(activeClass, inactiveClass);
                    }
                } else {
                    alert(data.message || 'Could not update project.');
                }
            });
        },
        setDefaultProject(projectId, btn) {
            fetch(`/projects/${projectId}/set-default`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
            }).then(r => r.json()).then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'Could not set default project.');
                }
            });
        },
    }));
});
</script>
</x-app-layout>
