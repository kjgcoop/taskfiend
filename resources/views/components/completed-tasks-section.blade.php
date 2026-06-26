@props([
    'tasks',
    'label' => 'Done tasks',
    'hideDate' => false,
    'readOnly' => false,
    'showAsArchived' => false,
    'totalCount' => null,
    'ajaxUrl' => null,
    'hasMore' => false,
    'nextPage' => 2,
])

@php
    $displayCount  = $totalCount ?? $tasks->count();
    $statusSection = $showAsArchived ? 'archived' : 'done';
@endphp

{{-- Always render so JS can inject newly-completed tasks. Hidden via x-show when empty. --}}
<div class="mt-4 border-t border-gray-700 pt-4"
     x-data="completedTasksLoader"
     x-show="totalCount > 0"
     @unless($displayCount > 0) style="display:none" @endunless
     data-status-section="{{ $statusSection }}"
     data-hide-date="{{ $hideDate ? 'true' : 'false' }}"
     data-has-more="{{ $hasMore ? 'true' : 'false' }}"
     data-next-page="{{ $nextPage }}"
     data-ajax-url="{{ $ajaxUrl }}"
     data-total-count="{{ $displayCount }}">
    <button type="button"
            @click="showCompleted = !showCompleted"
            class="inline-flex items-center gap-1.5 text-sm text-gray-400 hover:text-gray-200 transition-colors select-none">
        <svg xmlns="http://www.w3.org/2000/svg"
             class="h-3.5 w-3.5 transition-transform duration-150 flex-shrink-0"
             :class="showCompleted ? 'rotate-90' : ''"
             fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
        </svg>
        <span :class="showCompleted ? 'text-gray-200' : ''">
            {{ $label }}
            <span class="text-gray-600" x-text="visibleCount !== null ? '(' + visibleCount + ' of ' + totalCount + ')' : '(' + totalCount + ')'"></span>
        </span>
    </button>

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
<script nonce="{{ csp_nonce() }}">
document.addEventListener('alpine:init', () => {
    Alpine.data('completedTasksLoader', function () {
        return {
        showCompleted: false,
        hasMore:  false,
        nextPage: 2,
        loading:  false,
        totalCount: 0,
        visibleCount: null,
        _ajaxUrl: null,

        init() {
            const el = this.$el;
            this.hasMore = el.dataset.hasMore === 'true';
            this.nextPage = parseInt(el.dataset.nextPage) || 2;
            this.totalCount = parseInt(el.dataset.totalCount) || 0;
            this._ajaxUrl = el.dataset.ajaxUrl || null;
            window.addEventListener('filter-updated', () => this.updateCount());
        },

        updateCount() {
            if (!this.$refs.list) return;
            const tasks = this.$refs.list.querySelectorAll('[data-filterable]');
            const visible = Array.from(tasks).filter(el => el.style.display !== 'none').length;
            this.visibleCount = Alpine.store('taskCount').filtered ? visible : null;
        },

        async loadMore() {
            if (this.loading || !this.hasMore || !this._ajaxUrl) return;
            this.loading = true;
            try {
                const res  = await fetch(this._ajaxUrl + '?page=' + this.nextPage, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await res.json();
                this.$refs.list.insertAdjacentHTML('beforeend', data.html);
                this.hasMore  = data.hasMore;
                this.nextPage = data.nextPage;
                window.dispatchEvent(new CustomEvent('completed-tasks-loaded'));
            } catch (e) {
                console.error('Failed to load more completed tasks:', e);
            } finally {
                this.loading = false;
            }
        },
        };
    });
});
</script>
@endpush
@endonce
