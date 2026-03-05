@forelse($changeLogs as $log)
    <div class="border-l-2 border-gray-600 pl-4 py-2">
        <div class="flex items-start justify-between">
            <div class="flex-1">
                <p class="text-sm text-gray-300">
                    {{ $log->user->name }} {{ $log->description }}
                </p>
                <div class="flex items-center gap-2 mt-1">
                    <span class="text-xs text-gray-500">{{ $log->date->format('l, F j, Y g:i A') }}</span>
                </div>
            </div>
        </div>
    </div>
@empty
    <div class="text-center py-8 text-gray-500">
        No changes recorded yet.
    </div>
@endforelse
