@forelse($changeLogs as $log)
    <div class="border-l-2 border-gray-600 pl-4 py-2">
        <div class="flex items-start justify-between">
            <div class="flex-1">
                <p class="text-sm text-gray-300">
                    {{ $log->user->name }} {{ $log->description }}
                    @if(isset($log->entity))
                        @if($log->entity_type === 'tasks')
                            on task '<a href="{{ route('tasks.show', $log->entity) }}" class="text-blue-400 hover:underline">{{ $log->entity->name }}</a>'
                        @elseif($log->entity_type === 'projects')
                            on project '<a href="{{ route('projects.show', $log->entity) }}" class="text-blue-400 hover:underline">{{ $log->entity->name }}</a>'
                        @elseif($log->entity_type === 'tags')
                            on tag '<a href="{{ route('tags.show', $log->entity) }}" class="text-blue-400 hover:underline">{{ $log->entity->tag_name }}</a>'
                        @endif
                    @endif
                </p>
                <div class="flex items-center gap-2 mt-1">
                    <span class="text-xs px-1.5 py-0.5 rounded bg-gray-700 text-gray-400">{{ $log->entity_type }}</span>
                    <span class="text-xs text-gray-500">{{ $log->date->format('l, F j, Y g:i A') }}</span>
                </div>
            </div>
        </div>
    </div>
@empty
    <div class="text-center py-8 text-gray-500">
        No changes match your filters.
    </div>
@endforelse
