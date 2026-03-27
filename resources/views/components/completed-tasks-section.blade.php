@props([
    'tasks',
    'label' => 'Show done tasks',
    'hideDate' => false,
    'readOnly' => false,
    'showAsArchived' => false,
    'totalCount' => null,
    'ajaxUrl' => null,
    'hasMore' => false,
    'nextPage' => 2,
])

@php $displayCount = $totalCount ?? $tasks->count(); @endphp

@if($displayCount > 0)
<div class="mt-4 border-t border-gray-700 pt-4"
     x-data="completedTasksLoader({{ $hasMore ? 'true' : 'false' }}, {{ $nextPage }}, {{ json_encode($ajaxUrl) }})">
    <label class="inline-flex items-center gap-2 cursor-pointer select-none">
        <input type="checkbox"
               x-model="showCompleted"
               class="rounded border-gray-600 bg-gray-700 text-blue-600 focus:ring-blue-500 focus:ring-offset-0">
        <span class="text-sm text-gray-400" :class="showCompleted ? 'text-gray-200' : 'hover:text-gray-200'">
            {{ $label }}
            <span class="text-gray-600">({{ $displayCount }})</span>
        </span>
    </label>

    <div x-show="showCompleted" x-cloak class="mt-4">
        <div x-ref="list">
            <x-task-list :tasks="$tasks" :hide-date="$hideDate" :read-only="$readOnly" :show-as-archived="$showAsArchived" />
        </div>

        <div class="mt-4 text-center" x-show="hasMore">
            <button @click="loadMore()" :disabled="loading"
                    class="px-5 py-2 bg-gray-700 hover:bg-gray-600 text-gray-200 text-sm rounded-md disabled:opacity-50 disabled:cursor-wait">
                <span x-show="!loading">Load more</span>
                <span x-show="loading">Loading…</span>
            </button>
        </div>
    </div>
</div>

@once
@push('scripts')
<script>
function completedTasksLoader(initialHasMore, initialNextPage, ajaxUrl) {
    return {
        showCompleted: false,
        hasMore:  initialHasMore,
        nextPage: initialNextPage,
        loading:  false,

        async loadMore() {
            if (this.loading || !this.hasMore || !ajaxUrl) return;
            this.loading = true;
            try {
                const res  = await fetch(ajaxUrl + '?page=' + this.nextPage, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await res.json();
                this.$refs.list.insertAdjacentHTML('beforeend', data.html);
                this.hasMore  = data.hasMore;
                this.nextPage = data.nextPage;
            } catch (e) {
                console.error('Failed to load more completed tasks:', e);
            } finally {
                this.loading = false;
            }
        },
    };
}
</script>
@endpush
@endonce
@endif
