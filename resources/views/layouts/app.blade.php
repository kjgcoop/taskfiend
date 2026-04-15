<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="bg-black">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="session-check-interval" content="{{ config('session.check_interval', 60) }}">

        <!-- Restore saved sort preference before page renders (avoids visible re-sort) -->
        <script>(function(){var p=new URLSearchParams(window.location.search);if(!p.has('sort')){var s=localStorage.getItem('task_sort_'+window.location.pathname);if(s){p.set('sort',s);location.replace(location.pathname+'?'+p.toString());}}}());</script>

        <title>{{ config('app.name', 'Laravel') }} - {{ substr(strip_tags($header), 0, 100) }}</title>

        <!-- Favicon -->
        <link rel="icon" type="image/svg+xml" href="{{ config('app.env') !== 'production' ? '/favicon-dev.svg' : '/favicon.svg' }}">
        <link rel="icon" type="image/x-icon" href="/favicon.ico">

        <!-- Fonts -->
        <link rel="stylesheet" href="/css/fonts-figtree.css">

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <!-- Markdown rendering -->
        <script src="/js/vendor/marked.min.js"></script>
        <style>
            .markdown-body { color: #d1d5db; }
            .markdown-body h1, .markdown-body h2, .markdown-body h3,
            .markdown-body h4, .markdown-body h5, .markdown-body h6 {
                color: #f3f4f6; font-weight: 600; margin-top: 1em; margin-bottom: 0.5em; line-height: 1.25;
            }
            .markdown-body h1 { font-size: 1.5em; }
            .markdown-body h2 { font-size: 1.25em; }
            .markdown-body h3 { font-size: 1.125em; }
            .markdown-body p { margin-bottom: 0.75em; }
            .markdown-body p:last-child { margin-bottom: 0; }
            .markdown-body ul, .markdown-body ol { margin-left: 1.5em; margin-bottom: 0.75em; }
            .markdown-body ul { list-style-type: disc; }
            .markdown-body ol { list-style-type: decimal; }
            .markdown-body li { margin-bottom: 0.25em; }
            .markdown-body code {
                background-color: #374151; color: #d1d5db;
                padding: 0.15em 0.4em; border-radius: 0.25em;
                font-size: 0.875em; font-family: ui-monospace, monospace;
            }
            .markdown-body pre {
                background-color: #1f2937; padding: 0.75em 1em; border-radius: 0.375em;
                overflow-x: auto; margin-bottom: 0.75em; border: 1px solid #374151;
            }
            .markdown-body pre code { background-color: transparent; padding: 0; font-size: 0.875em; }
            .markdown-body blockquote {
                border-left: 3px solid #4b5563; padding-left: 1em;
                color: #9ca3af; margin-bottom: 0.75em; font-style: italic;
            }
            .markdown-body a { color: #60a5fa; text-decoration: underline; }
            .markdown-body a:hover { color: #93c5fd; }
            .markdown-body hr { border: none; border-top: 1px solid #4b5563; margin: 1em 0; }
            .markdown-body strong { color: #f3f4f6; font-weight: 600; }
            .markdown-body table { border-collapse: collapse; width: 100%; margin-bottom: 0.75em; }
            .markdown-body th, .markdown-body td { border: 1px solid #4b5563; padding: 0.5em 0.75em; text-align: left; }
            .markdown-body th { background-color: #374151; color: #f3f4f6; font-weight: 600; }
        </style>
        <style>
            /* Undo-completion toast */
            @keyframes toast-countdown {
                from { width: 100%; }
                to   { width: 0%; }
            }
            .toast-countdown-bar { animation: toast-countdown linear forwards; }

            /* Drag-and-drop task ordering */
            .task-drop-indicator {
                height: 2px;
                background: #3b82f6;
                border-radius: 1px;
                margin: -1px 0;
                display: none;
                pointer-events: none;
            }
            /* Show drag handle on touch devices (no hover state) */
            @media (hover: none) {
                .drag-handle { opacity: 0.4 !important; }
            }
        </style>
    </head>
    <body class="font-sans antialiased bg-black text-gray-100">
        <div class="min-h-screen bg-black">
            @if(config('app.env') !== 'production')
                <div class="fixed top-0 left-0 right-0 z-50 bg-red-600 text-white text-center text-xs font-bold tracking-widest py-1 uppercase">
                    {{ config('app.env') }} environment
                </div>
                <div class="h-6"></div>
            @endif

            @include('layouts.navigation')

            <!-- Page Heading -->
            @isset($header)
                <header class="bg-[#202020] shadow">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8 text-gray-100">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            <main>
                <div x-data="{
                        pad: 0,
                        init() {
                            const bar = document.getElementById('bulk-edit-bar');
                            if (!bar) return;
                            const update = () => { this.pad = bar.offsetHeight || 0; };
                            new ResizeObserver(update).observe(bar);
                            this.$watch(() => Alpine.store('bulkEdit').selected.length, update);
                        }
                     }"
                     :style="pad > 0 ? 'padding-bottom: ' + (pad + 16) + 'px' : ''"
                     @keydown.escape.window="$store.bulkEdit.exitIfActive()">
                    {{ $slot }}
                </div>
            </main>
        </div>

        @stack('scripts')

        <!-- Bulk Edit Bottom Bar -->
        <div id="bulk-edit-bar"
             x-data="bulkEditBar()"
             x-cloak
             x-show="$store.bulkEdit.active && $store.bulkEdit.selected.length > 0"
             x-transition:enter="transition ease-out duration-200 transform"
             x-transition:enter-start="translate-y-full opacity-0"
             x-transition:enter-end="translate-y-0 opacity-100"
             x-transition:leave="transition ease-in duration-150 transform"
             x-transition:leave-start="translate-y-0 opacity-100"
             x-transition:leave-end="translate-y-full opacity-0"
             style="display:none"
             class="fixed bottom-0 left-0 right-0 z-40 bg-gray-900 border-t border-gray-600 shadow-2xl">

            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3 flex items-center gap-4 flex-wrap">

                <!-- Selection count + select-all controls -->
                <div class="flex items-center gap-3 text-sm flex-shrink-0">
                    <span class="font-medium text-gray-100">
                        <span x-text="$store.bulkEdit.selected.length"></span>
                        <span x-text="$store.bulkEdit.selected.length === 1 ? ' task selected' : ' tasks selected'"></span>
                    </span>
                    <button @click="$store.bulkEdit.selectAllVisible()"
                            class="text-blue-400 hover:text-blue-300 underline text-xs">
                        Select all visible
                    </button>
                    <button @click="$store.bulkEdit.deselectAll()"
                            class="text-gray-500 hover:text-gray-300 underline text-xs">
                        Deselect all
                    </button>
                </div>

                <div class="flex-1 hidden sm:block"></div>

                <!-- Date input -->
                <div class="flex items-center gap-1.5">
                    <label class="text-xs text-gray-400 whitespace-nowrap">Date</label>
                    <input type="date" x-model="date" :disabled="clearDate"
                           @change="if (!date) clearDate = true"
                           min="{{ now()->format('Y-m-d') }}"
                           class="text-sm bg-gray-700 border border-gray-600 rounded px-2 py-1.5 text-gray-100 focus:outline-none focus:ring-1 focus:ring-blue-500 disabled:opacity-40 disabled:cursor-not-allowed">
                    <button @click="date = ''" x-show="date && !clearDate" title="Clear date input"
                            class="text-gray-500 hover:text-gray-300 text-xs w-5 h-5 flex items-center justify-center" style="display:none">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                    <label class="flex items-center gap-1 text-xs text-gray-400 cursor-pointer whitespace-nowrap ml-0.5" title="Remove date from all selected tasks">
                        <input type="checkbox" x-model="clearDate" @change="if (clearDate) date = ''"
                               class="rounded border-gray-500 bg-gray-600 text-blue-500 focus:ring-blue-500 focus:ring-offset-gray-900">
                        <span>Clear</span>
                    </label>
                </div>

                <!-- Project dropdown -->
                <div class="flex items-center gap-1.5">
                    <label class="text-xs text-gray-400 whitespace-nowrap">Project</label>
                    <select x-model="projectId"
                            class="text-sm bg-gray-700 border border-gray-600 rounded px-2 py-1.5 text-gray-100 focus:outline-none focus:ring-1 focus:ring-blue-500">
                        <option value="">— no change —</option>
                        <template x-for="project in $store.bulkEdit.projects" :key="project.id">
                            <option :value="project.id" x-text="project.name"></option>
                        </template>
                    </select>
                </div>

                <!-- Status dropdown -->
                <div class="flex items-center gap-1.5">
                    <label class="text-xs text-gray-400 whitespace-nowrap">Status</label>
                    <select x-model="status"
                            class="text-sm bg-gray-700 border border-gray-600 rounded px-2 py-1.5 text-gray-100 focus:outline-none focus:ring-1 focus:ring-blue-500">
                        <option value="">— no change —</option>
                        <option value="incomplete">Incomplete</option>
                        <option value="done">Done</option>
                        <option value="archived">Archived</option>
                    </select>
                </div>

                <!-- Tag multi-select -->
                <div class="flex items-center gap-1.5 relative" x-data="{ open: false }" @click.outside="open = false">
                    <label class="text-xs text-gray-400 whitespace-nowrap">Tags</label>
                    <button @click="open = !open"
                            type="button"
                            class="text-sm bg-gray-700 border border-gray-600 rounded px-2 py-1.5 text-gray-100 focus:outline-none focus:ring-1 focus:ring-blue-500 flex items-center gap-1.5 min-w-[130px]">
                        <span x-text="tagIds.length > 0 ? tagIds.length + ' tag' + (tagIds.length > 1 ? 's' : '') : '— add tags —'"
                              class="flex-1 text-left truncate"
                              :class="tagIds.length > 0 ? 'text-gray-100' : 'text-gray-500'"></span>
                        <svg class="w-3 h-3 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div x-show="open"
                         x-cloak
                         x-transition:enter="transition ease-out duration-100"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         class="absolute bottom-full mb-1 left-0 bg-gray-800 border border-gray-600 rounded-lg shadow-xl min-w-[180px] max-h-48 overflow-y-auto z-50"
                         style="display:none">
                        <template x-if="$store.bulkEdit.tags.length === 0">
                            <p class="px-3 py-2 text-sm text-gray-500 italic">No tags available</p>
                        </template>
                        <template x-for="tag in $store.bulkEdit.tags" :key="tag.id">
                            <label class="flex items-center gap-2 px-3 py-2 hover:bg-gray-700 cursor-pointer">
                                <input type="checkbox"
                                       :value="tag.id"
                                       :checked="tagIds.includes(tag.id)"
                                       @change="tagIds.includes(tag.id) ? tagIds.splice(tagIds.indexOf(tag.id), 1) : tagIds.push(tag.id)"
                                       class="rounded border-gray-500 bg-gray-600 text-blue-500 focus:ring-blue-500 focus:ring-offset-gray-800">
                                <span class="text-sm" :style="'color: ' + tag.color" x-text="tag.tag_name"></span>
                            </label>
                        </template>
                    </div>
                </div>

                <!-- Apply button -->
                <button @click="openConfirm()"
                        :disabled="!hasChanges"
                        :class="hasChanges
                            ? 'bg-blue-600 hover:bg-blue-700 text-white cursor-pointer'
                            : 'bg-gray-700 text-gray-500 cursor-not-allowed'"
                        class="px-4 py-2 rounded text-sm font-medium transition flex-shrink-0">
                    Apply
                </button>
            </div>

            <!-- Confirmation dialog -->
            <div x-show="confirming"
                 x-cloak
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 class="fixed inset-0 z-50 flex items-center justify-center bg-black/60"
                 style="display:none"
                 @keydown.escape.window="confirming = false">
                <div class="bg-gray-800 border border-gray-700 rounded-lg shadow-xl p-6 max-w-md w-full mx-4">
                    <h3 class="text-lg font-semibold text-gray-100 mb-3">Confirm bulk edit</h3>
                    <p class="text-gray-300 mb-6" x-text="confirmMessage"></p>
                    <div class="flex gap-3 justify-end">
                        <button @click="confirming = false"
                                class="px-4 py-2 bg-gray-700 text-gray-300 rounded hover:bg-gray-600 text-sm transition">
                            Cancel
                        </button>
                        <button @click="submit()"
                                :disabled="submitting"
                                :class="submitting ? 'opacity-60 cursor-wait' : 'hover:bg-blue-700'"
                                class="px-4 py-2 bg-blue-600 text-white rounded text-sm font-medium transition">
                            <span x-show="!submitting">Yes, apply changes</span>
                            <span x-show="submitting" style="display:none">Applying…</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.store('taskCount', {
                    total: 0,
                    visible: 0,
                    filtered: false,
                    ready: false
                });

                Alpine.store('bulkEdit', {
                    active: false,
                    selected: [],
                    projects: [],
                    tags: [],
                    toggle() {
                        this.active = !this.active;
                        if (!this.active) this.selected = [];
                    },
                    exitIfActive() {
                        if (this.active) this.toggle();
                    },
                    toggleTask(id) {
                        const idx = this.selected.indexOf(id);
                        if (idx >= 0) this.selected.splice(idx, 1);
                        else this.selected.push(id);
                    },
                    isSelected(id) { return this.selected.includes(id); },
                    selectAllVisible() {
                        const groups = document.querySelectorAll('[data-task-group-id]');
                        const ids = [];
                        groups.forEach(group => {
                            const filterable = group.querySelector('[data-filterable]');
                            if (filterable && filterable.style.display !== 'none' && group.offsetParent !== null) {
                                ids.push(parseInt(group.dataset.taskGroupId));
                            }
                        });
                        this.selected = ids;
                    },
                    deselectAll() { this.selected = []; },
                    get count() { return this.selected.length; }
                });
            });

            window.bulkEditBar = function () {
                return {
                    date: '',
                    clearDate: false,
                    projectId: '',
                    status: '',
                    tagIds: [],
                    confirming: false,
                    submitting: false,
                    confirmMessage: '',

                    get hasChanges() {
                        return this.date !== '' || this.clearDate || this.projectId !== '' || this.status !== '' || this.tagIds.length > 0;
                    },

                    openConfirm() {
                        if (!this.hasChanges) return;
                        const count = this.$store.bulkEdit.selected.length;
                        const fields = [];
                        if (this.date)            fields.push('due date');
                        if (this.clearDate)       fields.push('due date (cleared)');
                        if (this.projectId)       fields.push('project');
                        if (this.status)          fields.push('status');
                        if (this.tagIds.length > 0) fields.push('tags');

                        const fieldStr = fields.length === 1
                            ? fields[0]
                            : fields.slice(0, -1).join(', ') + ' and ' + fields[fields.length - 1];

                        this.confirmMessage = `You're about to change the ${fieldStr} on ${count} ${count === 1 ? 'task' : 'tasks'}. Are you sure?`;
                        this.confirming = true;
                    },

                    async submit() {
                        this.submitting = true;
                        try {
                            const formData = new FormData();
                            formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);

                            this.$store.bulkEdit.selected.forEach(id => {
                                formData.append('task_ids[]', id);
                            });

                            if (this.clearDate)  formData.append('clear_date', '1');
                            else if (this.date)  formData.append('date', this.date);
                            if (this.projectId)  formData.append('project_id', this.projectId);
                            if (this.status)     formData.append('status', this.status);
                            this.tagIds.forEach(id => formData.append('tag_ids[]', id));

                            const response = await fetch('/tasks/bulk-update', {
                                method: 'POST',
                                body: formData,
                            });

                            const data = await response.json();

                            if (data.success) {
                                this.confirming = false;
                                this.$store.bulkEdit.toggle(); // exit bulk mode & clear selection
                                window.location.reload();
                            } else {
                                alert('Error: ' + (data.message || 'Failed to update tasks.'));
                                this.confirming = false;
                            }
                        } catch (e) {
                            alert('An error occurred. Please try again.');
                            this.confirming = false;
                        } finally {
                            this.submitting = false;
                        }
                    },
                };
            };

        </script>

        <!-- Task Side Panel Overlay -->
        <div id="task-panel-overlay"
             x-data="taskPanelOverlay()"
             @open-task-panel.window="openTask($event.detail.taskId)"
             @close-task-panel.window="close()"
             @reload-task-panel.window="openTask($event.detail.taskId)"
             x-show="open"
             x-cloak
             class="fixed inset-0 z-50 flex justify-end"
             style="display: none;">

            <!-- Backdrop -->
            <div class="absolute inset-0 bg-black bg-opacity-60"
                 @click="close()"
                 x-transition:enter="transition-opacity ease-out duration-200"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="transition-opacity ease-in duration-150"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0">
            </div>

            <!-- Panel drawer -->
            <div class="relative z-10 flex flex-col w-[90vw] max-w-[90vw] h-full bg-gray-900 border-l border-gray-700 shadow-2xl overflow-y-auto"
                 x-transition:enter="transition-transform ease-out duration-200"
                 x-transition:enter-start="translate-x-full"
                 x-transition:enter-end="translate-x-0"
                 x-transition:leave="transition-transform ease-in duration-150"
                 x-transition:leave-start="translate-x-0"
                 x-transition:leave-end="translate-x-full"
                 @keydown.escape.window="close()">

                <!-- Loading spinner -->
                <div x-show="loading" class="flex items-center justify-center flex-1 min-h-32">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
                </div>

                <!-- Error state -->
                <div x-show="error" x-cloak class="p-6 text-red-400 text-sm">
                    Failed to load task. <button @click="close()" class="underline">Close</button>
                </div>

                <!-- Injected panel content -->
                <div x-show="!loading && !error">
                    <div id="task-panel-content"></div>
                </div>

            </div>
        </div>

        <script>
            window.taskPanelOverlay = function () {
                return {
                    open: false,
                    loading: false,
                    error: false,
                    currentTaskId: null,
                    _pushedEntry: false, // true when we own the current history entry

                    init() {
                        window.addEventListener('popstate', (e) => {
                            if (this.open) {
                                // Back pressed while panel is open — close the panel
                                this._closeWithoutHistory();
                            } else if (e.state && e.state.taskPanel && e.state.taskId) {
                                // Forward pressed back to a panel state — reopen without pushing again
                                this._openFromHistory(e.state.taskId);
                            }
                        });

                        // Auto-open if the page was loaded with ?task= in the URL (e.g. pasted link)
                        const taskId = new URLSearchParams(location.search).get('task');
                        if (taskId) {
                            this._openFromPageLoad(parseInt(taskId));
                        }
                    },

                    // Normal open triggered by clicking a task in the list
                    async openTask(taskId) {
                        const wasAlreadyOpen = this.open;
                        this.open = true;
                        this.loading = true;
                        this.error = false;
                        this.currentTaskId = taskId;

                        const taskUrl = this._urlWithTask(taskId);

                        if (!wasAlreadyOpen) {
                            history.pushState({ taskPanel: true, taskId }, '', taskUrl);
                            this._pushedEntry = true;
                        } else {
                            // Panel already open (reloading after a save) — update URL in place
                            history.replaceState({ taskPanel: true, taskId }, '', taskUrl);
                        }

                        await this._loadContent(taskId);
                    },

                    // Opened because the page URL already contained ?task= on load
                    async _openFromPageLoad(taskId) {
                        this.open = true;
                        this.loading = true;
                        this.error = false;
                        this.currentTaskId = taskId;

                        // Insert a clean base entry before the panel entry so back restores the bare URL
                        const baseUrl = this._urlWithoutTask();
                        history.replaceState({}, '', baseUrl);
                        history.pushState({ taskPanel: true, taskId }, '', this._urlWithTask(taskId));
                        this._pushedEntry = true;

                        await this._loadContent(taskId);
                    },

                    // Opened because the user pressed the forward button to a panel history entry
                    async _openFromHistory(taskId) {
                        this.open = true;
                        this.loading = true;
                        this.error = false;
                        this.currentTaskId = taskId;
                        this._pushedEntry = true; // entry already exists, back will still work

                        await this._loadContent(taskId);
                    },

                    async _loadContent(taskId) {
                        try {
                            const res = await fetch(`/tasks/${taskId}/panel`, {
                                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                            });
                            if (!res.ok) throw new Error('HTTP ' + res.status);
                            const html = await res.text();

                            const content = document.getElementById('task-panel-content');
                            if (window.Alpine && typeof Alpine.destroyTree === 'function') {
                                Alpine.destroyTree(content);
                            }
                            content.innerHTML = html;
                            content.querySelectorAll('script').forEach(oldScript => {
                                const newScript = document.createElement('script');
                                Array.from(oldScript.attributes).forEach(attr => {
                                    newScript.setAttribute(attr.name, attr.value);
                                });
                                newScript.textContent = oldScript.textContent;
                                oldScript.replaceWith(newScript);
                            });
                            if (window.Alpine) {
                                Alpine.initTree(content);
                            }
                        } catch (e) {
                            console.error('Panel load error:', e);
                            this.error = true;
                        } finally {
                            this.loading = false;
                        }
                    },

                    close() {
                        if (!this.open) return;
                        if (this._pushedEntry) {
                            history.back(); // popstate fires → _closeWithoutHistory()
                        } else {
                            // Fallback: clean URL manually and close
                            history.replaceState({}, '', this._urlWithoutTask());
                            this._closeWithoutHistory();
                        }
                    },

                    _closeWithoutHistory() {
                        this.open = false;
                        this.currentTaskId = null;
                        this._pushedEntry = false;
                        setTimeout(() => {
                            const content = document.getElementById('task-panel-content');
                            if (content) {
                                if (window.Alpine && typeof Alpine.destroyTree === 'function') {
                                    Alpine.destroyTree(content);
                                }
                                content.innerHTML = '';
                            }
                            this.error = false;
                        }, 200);
                    },

                    _urlWithTask(taskId) {
                        const u = new URL(location.href);
                        u.searchParams.set('task', taskId);
                        return u.toString();
                    },

                    _urlWithoutTask() {
                        const u = new URL(location.href);
                        u.searchParams.delete('task');
                        return u.toString();
                    },
                };
            };

            // Global helpers so panel content and task list can trigger the panel
            window.openTaskPanel = function (taskId) {
                window.dispatchEvent(new CustomEvent('open-task-panel', { detail: { taskId } }));
            };
            window.closeTaskPanel = function () {
                window.dispatchEvent(new CustomEvent('close-task-panel'));
            };
            window.reloadTaskPanel = function (taskId) {
                window.dispatchEvent(new CustomEvent('reload-task-panel', { detail: { taskId } }));
            };

            // ─── Drag-and-drop task reordering (pointer events — works on touch too) ──
            window.initTaskSortable = function (container) {
                let draggedEl = null;
                let ghost = null;
                let offsetY = 0;
                let isDragging = false;

                const indicator = document.createElement('div');
                indicator.className = 'task-drop-indicator';

                function isBulkActive() {
                    try { return Alpine.store('bulkEdit').active; } catch { return false; }
                }

                container.addEventListener('pointerdown', (e) => {
                    if (isBulkActive()) return;
                    const handle = e.target.closest('.drag-handle');
                    if (!handle) return;
                    const group = handle.closest('[data-task-group]');
                    if (!group) return;

                    e.preventDefault(); // prevents scroll on touch while on handle
                    draggedEl = group;
                    isDragging = false;

                    const rect = group.getBoundingClientRect();
                    offsetY = e.clientY - rect.top;

                    ghost = group.cloneNode(true);
                    ghost.style.cssText = 'position:fixed;left:' + rect.left + 'px;top:' + rect.top + 'px;width:' + rect.width + 'px;opacity:0.5;pointer-events:none;z-index:9999;box-sizing:border-box;';
                    document.body.appendChild(ghost);
                    draggedEl.style.opacity = '0.3';

                    try { container.setPointerCapture(e.pointerId); } catch (_) {}
                    container.addEventListener('pointermove', onMove);
                    container.addEventListener('pointerup', onUp);
                    container.addEventListener('pointercancel', onCancel);
                });

                function onMove(e) {
                    if (!draggedEl) return;
                    isDragging = true;
                    ghost.style.top = (e.clientY - offsetY) + 'px';

                    // Temporarily hide ghost so elementFromPoint can see what's underneath
                    ghost.style.visibility = 'hidden';
                    const el = document.elementFromPoint(e.clientX, e.clientY);
                    ghost.style.visibility = '';
                    if (!el) return;

                    const target = el.closest('[data-task-group]');
                    if (!target || target === draggedEl || !container.contains(target)) return;

                    const rect = target.getBoundingClientRect();
                    if (e.clientY < rect.top + rect.height / 2) {
                        container.insertBefore(indicator, target);
                    } else {
                        container.insertBefore(indicator, target.nextSibling);
                    }
                    indicator.style.display = 'block';
                }

                function onUp() {
                    if (!draggedEl) return;
                    container.removeEventListener('pointermove', onMove);
                    container.removeEventListener('pointerup', onUp);
                    container.removeEventListener('pointercancel', onCancel);
                    if (isDragging && indicator.parentNode) {
                        container.insertBefore(draggedEl, indicator);
                        cleanup();
                        saveOrder();
                    } else {
                        cleanup();
                    }
                }

                function onCancel() {
                    container.removeEventListener('pointermove', onMove);
                    container.removeEventListener('pointerup', onUp);
                    container.removeEventListener('pointercancel', onCancel);
                    cleanup();
                }

                function cleanup() {
                    if (ghost) { ghost.remove(); ghost = null; }
                    if (draggedEl) { draggedEl.style.opacity = ''; draggedEl = null; }
                    isDragging = false;
                    indicator.style.display = 'none';
                    if (indicator.parentNode) indicator.parentNode.removeChild(indicator);
                }

                function saveOrder() {
                    const ids = [...container.querySelectorAll(':scope > [data-task-group-id]')]
                        .map(el => el.dataset.taskGroupId);
                    const url = container.dataset.reorderUrl || '/tasks/reorder';
                    fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({ ids }),
                    }).catch(() => {});
                }
            };
            // ─────────────────────────────────────────────────────────────────────────
        </script>

        <!-- Undo-completion toast stack -->
        <div x-data="undoToastManager()"
             @task-completed.window="add($event.detail)"
             class="fixed bottom-4 right-4 z-50 flex flex-col-reverse gap-2 items-end pointer-events-none"
             aria-live="polite">
            <template x-for="toast in toasts" :key="toast.id">
                <div class="pointer-events-auto w-72 bg-gray-800 border border-gray-700 rounded-lg shadow-2xl overflow-hidden"
                     x-show="toast.visible"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 translate-y-1"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     x-transition:leave="transition ease-in duration-150"
                     x-transition:leave-start="opacity-100"
                     x-transition:leave-end="opacity-0">
                    <div class="flex items-center gap-3 px-3 py-2.5">
                        <div class="w-5 h-5 rounded-full bg-green-600 flex-shrink-0 flex items-center justify-center">
                            <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-gray-300 truncate" x-text="toast.taskName"></p>
                            <p x-show="toast.recurring" class="text-xs text-purple-400 mt-0.5">Next instance created</p>
                        </div>
                        <button x-show="!toast.recurring"
                                @click="undo(toast)"
                                class="flex-shrink-0 px-2.5 py-1 text-xs font-medium bg-gray-700 hover:bg-gray-600 text-gray-200 rounded transition-colors">
                            Undo
                        </button>
                    </div>
                    <div class="h-0.5 bg-gray-700">
                        <div class="h-full bg-green-500 toast-countdown-bar"
                             :style="`animation-duration: ${toast.duration}ms`"></div>
                    </div>
                </div>
            </template>
        </div>

        <script>
            window.undoToastManager = function () {
                return {
                    toasts: [],

                    add({ taskId, taskName, undoUrl, recurring, group, form }) {
                        const duration = {{ config('app.undo_toast_duration') }};
                        const toast = {
                            id: Date.now() + Math.random(),
                            taskId, taskName, undoUrl, recurring, group, form,
                            duration,
                            visible: true,
                            timer: null,
                        };
                        toast.timer = setTimeout(() => this._expire(toast), duration);
                        this.toasts.push(toast);
                    },

                    _expire(toast) {
                        // Countdown finished — fully hide the task row
                        if (toast.group) {
                            toast.group.style.opacity = '0';
                            setTimeout(() => { toast.group.style.display = 'none'; }, 300);
                        }
                        this._dismiss(toast);
                    },

                    _dismiss(toast) {
                        toast.visible = false;
                        clearTimeout(toast.timer);
                        setTimeout(() => {
                            this.toasts = this.toasts.filter(t => t.id !== toast.id);
                        }, 200);
                    },

                    async undo(toast) {
                        // Restore the task row immediately
                        if (toast.group) {
                            toast.group.style.transition = '';
                            toast.group.style.display = '';
                            toast.group.style.opacity = '1';
                            toast.group.style.pointerEvents = '';
                        }
                        // Reset the filled circle back to the empty "incomplete" state
                        if (toast.form) {
                            toast.form.dispatchEvent(new CustomEvent('undo-complete', { bubbles: false }));
                        }
                        this._dismiss(toast);

                        // Tell the server
                        try {
                            const fd = new FormData();
                            fd.append('_token', document.querySelector('meta[name="csrf-token"]').content);
                            fd.append('field', 'status');
                            fd.append('value', 'incomplete');
                            await fetch(toast.undoUrl, {
                                method: 'POST',
                                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                                body: fd,
                            });
                        } catch {
                            // Server call failed — re-dim the row so the user knows it didn't save
                            if (toast.group) {
                                toast.group.style.opacity = '0.3';
                                toast.group.style.pointerEvents = 'none';
                            }
                        }
                    },
                };
            };
        </script>

        <script>
            (function () {
                const seconds = parseInt(
                    document.querySelector('meta[name="session-check-interval"]')?.content || '60',
                    10
                );
                if (!seconds || seconds <= 0) return;

                setInterval(async function () {
                    try {
                        const res = await fetch('/auth/check', { credentials: 'same-origin' });
                        if (!res.ok) {
                            window.location.href = '/login';
                        }
                    } catch (_) {
                        // Network error — don't redirect; the server may just be temporarily unreachable.
                    }
                }, seconds * 1000);
            })();
        </script>
    </body>
</html>
