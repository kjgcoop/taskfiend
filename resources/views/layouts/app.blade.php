<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="bg-black">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Favicon -->
        <link rel="icon" type="image/svg+xml" href="{{ config('app.env') !== 'production' ? '/favicon-dev.svg' : '/favicon.svg' }}">
        <link rel="icon" type="image/x-icon" href="/favicon.ico">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <!-- Markdown rendering -->
        <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
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
                {{ $slot }}
            </main>
        </div>

        @stack('scripts')

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

                    init() {
                        // Intercept browser back button while panel is open
                        window.addEventListener('popstate', (e) => {
                            if (this.open) {
                                // Panel is open: closing it IS the back action; don't navigate further
                                this._closeWithoutHistory();
                            }
                        });
                    },

                    async openTask(taskId) {
                        const wasAlreadyOpen = this.open;
                        this.open = true;
                        this.loading = true;
                        this.error = false;
                        this.currentTaskId = taskId;

                        // Push a history entry so back button can peel the panel
                        if (!wasAlreadyOpen) {
                            history.pushState({ taskPanel: true, taskId }, '');
                        } else {
                            // Replace so we don't stack entries when reloading panel content
                            history.replaceState({ taskPanel: true, taskId }, '');
                        }

                        try {
                            const res = await fetch(`/tasks/${taskId}/panel`, {
                                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                            });
                            if (!res.ok) throw new Error('HTTP ' + res.status);
                            const html = await res.text();

                            const content = document.getElementById('task-panel-content');
                            // Destroy any existing Alpine tree before replacing content
                            if (window.Alpine && typeof Alpine.destroyTree === 'function') {
                                Alpine.destroyTree(content);
                            }
                            content.innerHTML = html;
                            // Execute script tags in injected HTML
                            content.querySelectorAll('script').forEach(oldScript => {
                                const newScript = document.createElement('script');
                                Array.from(oldScript.attributes).forEach(attr => {
                                    newScript.setAttribute(attr.name, attr.value);
                                });
                                newScript.textContent = oldScript.textContent;
                                oldScript.replaceWith(newScript);
                            });
                            // Init Alpine.js on new content
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
                        // Navigate back in history — the popstate handler calls _closeWithoutHistory()
                        if (this.open) {
                            history.back();
                        }
                    },

                    _closeWithoutHistory() {
                        this.open = false;
                        this.currentTaskId = null;
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
        </script>
    </body>
</html>
