@forelse($logs as $log)
    <div class="px-4 py-3 border-b border-gray-700 last:border-0 hover:bg-gray-700/40 transition-colors">
        <p class="text-sm text-gray-200 leading-snug">
            <span class="font-medium text-gray-100">{{ $log->user->name }}</span>
            {{ $log->description }}
            @if(isset($log->entity))
                @if($log->entity_type === 'tasks')
                    on <a href="{{ route('tasks.show', $log->entity) }}" class="text-blue-400 hover:underline">{{ $log->entity->name }}</a>
                @elseif($log->entity_type === 'projects')
                    on <a href="{{ route('projects.show', $log->entity) }}" class="text-blue-400 hover:underline">{{ $log->entity->name }}</a>
                @endif
            @endif
        </p>
        <p class="text-xs text-gray-500 mt-0.5">{{ $log->date->diffForHumans() }}</p>
    </div>
@empty
    <div class="px-4 py-6 text-center text-sm text-gray-500">
        No recent activity from collaborators.
    </div>
@endforelse
