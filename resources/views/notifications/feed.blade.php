@forelse($notifications as $notif)
    <div class="px-4 py-3 border-b border-gray-700 last:border-0 hover:bg-gray-700/40 transition-colors {{ $notif->seen ? 'opacity-60' : '' }}">
        <p class="text-sm text-gray-200 leading-snug">
            <span class="font-medium text-gray-100">{{ $notif->actor_name }}</span>
            {{ $notif->description }}
            @if($notif->entity_type === 'tasks')
                on <a href="{{ route('tasks.show', $notif->entity_id) }}" class="text-blue-400 hover:underline">{{ $notif->entity_name }}</a>
            @elseif($notif->entity_type === 'projects')
                on <a href="{{ route('projects.show', $notif->entity_id) }}" class="text-blue-400 hover:underline">{{ $notif->entity_name }}</a>
            @endif
        </p>
        <p class="text-xs text-gray-500 mt-0.5">{{ $notif->created_at->diffForHumans() }}</p>
    </div>
@empty
    <div class="px-4 py-6 text-center text-sm text-gray-500">
        No recent activity from collaborators.
    </div>
@endforelse
