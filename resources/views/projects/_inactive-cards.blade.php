@foreach($projects as $project)
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
