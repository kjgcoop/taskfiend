@props(['tasks', 'depth' => 0, 'hideDate' => false, 'readOnly' => false, 'viewDate' => null, 'showAsArchived' => false, 'sortable' => false, 'reorderUrl' => null])

@pushOnce('scripts')
<script>
    window.taskPreviewUrl = '{{ route('tasks.previewQuickAdd') }}';

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

    window.listQuickComplete = function () {
        return {
            done: false,
            loading: false,
            error: null,
            async submit() {
                this.loading = true;
                this.error = null;
                const form = this.$el;
                try {
                    const res = await fetch(form.action, {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        body: new FormData(form),
                    });
                    const data = await res.json().catch(() => ({}));
                    if (res.ok && data.ok !== false && data.success !== false) {
                        this.done = true;
                        // Brief pause to show filled dot, then disappear and hand off to the undo toast
                        await new Promise(r => setTimeout(r, 400));
                        const group = form.closest('[data-task-group]');
                        if (group) {
                            group.style.transition = 'opacity 0.2s';
                            group.style.opacity = '0';
                            setTimeout(() => { group.style.display = 'none'; }, 200);
                        }
                        window.dispatchEvent(new CustomEvent('task-completed', {
                            detail: {
                                taskId:    form.dataset.taskId,
                                taskName:  form.dataset.taskName,
                                undoUrl:   form.dataset.undoUrl,
                                recurring: form.dataset.recurring === 'true',
                                group,
                                form,
                            }
                        }));
                    } else {
                        this.error = data.message || 'Could not complete task. Please try again.';
                    }
                } catch {
                    form.submit(); // network failure – fall back to full reload
                } finally {
                    this.loading = false;
                }
            }
        };
    };

    window.taskFilter = function (projects, tags, users, locations) {
        return {
            query: '',
            noResults: false,
            showIncomplete: true,
            mode: 'create',
            nameError: '',
            serverError: '',
            submitting: false,

            // Autocomplete state
            projects: projects || [],
            tags: tags || [],
            users: users || [],
            locations: locations || [],
            showAutocomplete: false,
            autocompleteType: null,
            autocompleteQuery: '',
            autocompleteIndex: 0,
            autocompleteLocationMap: false,    // true when triggered by ++ (map link)
            autocompleteLocationQuoted: false, // true when triggered by +" or ++" (quoted form)
            autocompleteNot: false,            // true when triggered by not: prefix in filter mode

            // Quick-add parse preview
            preview: null,
            previewTimer: null,

            get filteredProjects() {
                if (!this.autocompleteQuery) return this.projects;
                const q = this.autocompleteQuery.toLowerCase();
                return this.projects.filter(p => p.name.toLowerCase().includes(q));
            },

            get filteredTags() {
                if (!this.autocompleteQuery) return this.tags;
                const q = this.autocompleteQuery.toLowerCase();
                return this.tags.filter(t => t.tag_name.toLowerCase().includes(q));
            },

            get filteredLocations() {
                if (!this.autocompleteQuery) return this.locations;
                const q = this.autocompleteQuery.toLowerCase().replace(/-/g, ' ');
                return this.locations.filter(l => l.toLowerCase().includes(q));
            },

            get filteredUsers() {
                if (!this.autocompleteQuery) return this.users;
                const q = this.autocompleteQuery.toLowerCase();
                return this.users.filter(u => u.name.toLowerCase().includes(q));
            },

            // Number of non-empty task lines currently in the textarea
            get lineCount() {
                const input = this.$refs.createInput;
                if (!input) return 1;
                return input.value.split('\n').filter(l => l.trim().length > 0).length;
            },

            async validateAndSubmit(event) {
                const form = event.target;
                const input = this.$refs.createInput;
                if (!input || this.submitting) return;
                const fullValue = input.value;
                const lines = fullValue.split('\n').map(l => l.trim()).filter(l => l.length > 0);
                if (lines.length === 0) return;

                // Per-line length validation
                const tooLong = lines.find(l => l.length > 255);
                if (tooLong) {
                    this.nameError = `A task name is too long (${tooLong.length}/255 characters max).`;
                    return;
                }

                this.nameError = '';
                this.serverError = '';
                clearTimeout(this.previewTimer);
                this.preview = null;

                if (lines.length === 1) {
                    // Single task: remove autocomplete-injected hidden inputs whose token was deleted
                    form.querySelectorAll('input[data-tag-slug]').forEach(hidden => {
                        if (!fullValue.includes('@' + hidden.dataset.tagSlug)) hidden.remove();
                    });
                    const projectHidden = form.querySelector('input[data-project-autocomplete]');
                    if (projectHidden && !fullValue.includes('#' + projectHidden.dataset.projectSlug)) {
                        projectHidden.remove();
                    }
                } else {
                    // Multi-line: server parses each line independently; drop autocomplete
                    // injected inputs so they don't erroneously apply to every task.
                    form.querySelectorAll('input[data-tag-slug]').forEach(h => h.remove());
                    const projectHidden = form.querySelector('input[data-project-autocomplete]');
                    if (projectHidden) projectHidden.remove();
                }

                // Stale-page date override: if viewing a past date, use today's date as the
                // fallback so tasks land on the current day. Date keywords typed into the task
                // name (e.g. "buy milk tomorrow") still take precedence on the server side.
                const dateInput = form.querySelector('input[name="date"]');
                if (dateInput && dateInput.value) {
                    const now = new Date();
                    const todayLocal = now.getFullYear() + '-' +
                        String(now.getMonth() + 1).padStart(2, '0') + '-' +
                        String(now.getDate()).padStart(2, '0');
                    if (dateInput.value < todayLocal) {
                        sessionStorage.setItem('staleTaskCreated', todayLocal);
                        dateInput.value = todayLocal;
                    }
                }

                this.submitting = true;
                try {
                    const res = await fetch(form.action, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: new FormData(form),
                    });
                    if (res.ok) {
                        const data = await res.json().catch(() => ({}));
                        // Partial bulk success: persist the error message across the reload
                        if (data.partial && data.message) {
                            sessionStorage.setItem('quickAddBulkError', data.message);
                        }
                        window.location.reload();
                        return;
                    }
                    const data = await res.json().catch(() => ({}));
                    if (data.errors) {
                        const msgs = Object.values(data.errors).flat();
                        this.serverError = msgs.join(' · ');
                    } else {
                        this.serverError = data.message || 'Could not create task. Please try again.';
                    }
                } catch {
                    // Network failure — fall back to regular form submit
                    form.submit();
                } finally {
                    this.submitting = false;
                }
            },

            handleInput(event) {
                const el = event.target;
                this.autocompleteNot = false;
                const input = el.value;
                if (this.serverError) this.serverError = '';

                // Per-line length validation (each task name ≤ 255)
                const lines = input.split('\n').map(l => l.trim()).filter(l => l.length > 0);
                const tooLong = lines.find(l => l.length > 255);
                this.nameError = tooLong
                    ? `A task name is too long (${tooLong.length}/255 characters max).`
                    : '';

                // Auto-resize textarea to fit content
                el.style.height = 'auto';
                el.style.height = Math.min(el.scrollHeight, 200) + 'px';

                // Autocomplete: scope to the text on the current line before the cursor
                const cursorPos = el.selectionStart;
                const lineStart = input.lastIndexOf('\n', cursorPos - 1) + 1;
                const beforeCursor = input.substring(lineStart, cursorPos);

                const projectMatch        = beforeCursor.match(/#(\w*)$/);
                const tagMatch           = beforeCursor.match(/@(\w*)$/);
                // Location: check quoted ++ and + before unquoted to avoid false matches
                const mapLocQuotedMatch  = beforeCursor.match(/(?<!\S)\+\+"([^"]*)$/);
                const mapLocUnquotMatch  = !mapLocQuotedMatch && beforeCursor.match(/(?<!\S)\+\+(\w*)$/);
                const locQuotedMatch     = !mapLocQuotedMatch && !mapLocUnquotMatch && beforeCursor.match(/(?<!\S)\+"([^"]*)$/);
                const locationMatch      = !mapLocQuotedMatch && !mapLocUnquotMatch && !locQuotedMatch && beforeCursor.match(/(?<!\S)\+(\w*)$/);
                const userMatch          = beforeCursor.match(/&(\w*)$/);

                if (projectMatch && this.projects.length > 0) {
                    this.autocompleteType = 'project';
                    this.autocompleteQuery = projectMatch[1];
                    this.autocompleteIndex = 0;
                    this.autocompleteLocationMap = false;
                    this.autocompleteLocationQuoted = false;
                    this.showAutocomplete = this.filteredProjects.length > 0;
                } else if (tagMatch && this.tags.length > 0) {
                    this.autocompleteType = 'tag';
                    this.autocompleteQuery = tagMatch[1];
                    this.autocompleteIndex = 0;
                    this.autocompleteLocationMap = false;
                    this.autocompleteLocationQuoted = false;
                    this.showAutocomplete = this.filteredTags.length > 0;
                } else if (mapLocQuotedMatch) {
                    this.autocompleteType = 'location';
                    this.autocompleteQuery = mapLocQuotedMatch[1];
                    this.autocompleteIndex = 0;
                    this.autocompleteLocationMap = true;
                    this.autocompleteLocationQuoted = true;
                    this.showAutocomplete = this.filteredLocations.length > 0;
                } else if (mapLocUnquotMatch) {
                    this.autocompleteType = 'location';
                    this.autocompleteQuery = mapLocUnquotMatch[1];
                    this.autocompleteIndex = 0;
                    this.autocompleteLocationMap = true;
                    this.autocompleteLocationQuoted = false;
                    this.showAutocomplete = this.filteredLocations.length > 0;
                } else if (locQuotedMatch) {
                    this.autocompleteType = 'location';
                    this.autocompleteQuery = locQuotedMatch[1];
                    this.autocompleteIndex = 0;
                    this.autocompleteLocationMap = false;
                    this.autocompleteLocationQuoted = true;
                    this.showAutocomplete = this.filteredLocations.length > 0;
                } else if (locationMatch) {
                    this.autocompleteType = 'location';
                    this.autocompleteQuery = locationMatch[1];
                    this.autocompleteIndex = 0;
                    this.autocompleteLocationMap = false;
                    this.autocompleteLocationQuoted = false;
                    this.showAutocomplete = this.filteredLocations.length > 0;
                } else if (userMatch && this.users.length > 0) {
                    this.autocompleteType = 'user';
                    this.autocompleteQuery = userMatch[1];
                    this.autocompleteIndex = 0;
                    this.autocompleteLocationMap = false;
                    this.autocompleteLocationQuoted = false;
                    this.showAutocomplete = this.filteredUsers.length > 0;
                } else {
                    this.showAutocomplete = false;
                }

                // Use $nextTick so the browser fully settles cursor position
                // before we sample it (important for paste events).
                this.$nextTick(() => this.schedulePreview(el.value, el.selectionStart));
            },

            handleFilterInput(event) {
                const el = event.target;
                const beforeCursor = el.value.substring(0, el.selectionStart);

                const notProjectMatch = beforeCursor.match(/not:#(\w*)$/i);
                const notTagMatch     = beforeCursor.match(/not:@(\w*)$/i);
                const notLocMatch     = beforeCursor.match(/not:\+(\w*)$/i);
                const notUserMatch    = beforeCursor.match(/not:&(\w*)$/i);
                const projectMatch    = !notProjectMatch && beforeCursor.match(/#(\w*)$/);
                const tagMatch        = !notTagMatch && beforeCursor.match(/@(\w*)$/);
                const locationMatch   = !notLocMatch && beforeCursor.match(/(?<!\S)\+(\w*)$/);
                const userMatch       = !notUserMatch && beforeCursor.match(/&(\w*)$/);

                if (notProjectMatch && this.projects.length > 0) {
                    this.autocompleteType = 'project';
                    this.autocompleteQuery = notProjectMatch[1];
                    this.autocompleteNot = true;
                    this.autocompleteIndex = 0;
                    this.showAutocomplete = this.filteredProjects.length > 0;
                } else if (notTagMatch && this.tags.length > 0) {
                    this.autocompleteType = 'tag';
                    this.autocompleteQuery = notTagMatch[1];
                    this.autocompleteNot = true;
                    this.autocompleteIndex = 0;
                    this.showAutocomplete = this.filteredTags.length > 0;
                } else if (notLocMatch) {
                    this.autocompleteType = 'location';
                    this.autocompleteQuery = notLocMatch[1];
                    this.autocompleteNot = true;
                    this.autocompleteLocationMap = false;
                    this.autocompleteLocationQuoted = false;
                    this.autocompleteIndex = 0;
                    this.showAutocomplete = this.filteredLocations.length > 0;
                } else if (notUserMatch && this.users.length > 0) {
                    this.autocompleteType = 'user';
                    this.autocompleteQuery = notUserMatch[1];
                    this.autocompleteNot = true;
                    this.autocompleteIndex = 0;
                    this.showAutocomplete = this.filteredUsers.length > 0;
                } else if (projectMatch && this.projects.length > 0) {
                    this.autocompleteType = 'project';
                    this.autocompleteQuery = projectMatch[1];
                    this.autocompleteNot = false;
                    this.autocompleteIndex = 0;
                    this.showAutocomplete = this.filteredProjects.length > 0;
                } else if (tagMatch && this.tags.length > 0) {
                    this.autocompleteType = 'tag';
                    this.autocompleteQuery = tagMatch[1];
                    this.autocompleteNot = false;
                    this.autocompleteIndex = 0;
                    this.showAutocomplete = this.filteredTags.length > 0;
                } else if (locationMatch) {
                    this.autocompleteType = 'location';
                    this.autocompleteQuery = locationMatch[1];
                    this.autocompleteNot = false;
                    this.autocompleteLocationMap = false;
                    this.autocompleteLocationQuoted = false;
                    this.autocompleteIndex = 0;
                    this.showAutocomplete = this.filteredLocations.length > 0;
                } else if (userMatch && this.users.length > 0) {
                    this.autocompleteType = 'user';
                    this.autocompleteQuery = userMatch[1];
                    this.autocompleteNot = false;
                    this.autocompleteIndex = 0;
                    this.showAutocomplete = this.filteredUsers.length > 0;
                } else {
                    this.showAutocomplete = false;
                    this.autocompleteNot = false;
                }

                this.filterTasks();
            },

            handleFilterKeydown(event) {
                if (this.showAutocomplete) {
                    const list = this.autocompleteType === 'project'  ? this.filteredProjects
                               : this.autocompleteType === 'tag'      ? this.filteredTags
                               : this.autocompleteType === 'location' ? this.filteredLocations
                               : this.filteredUsers;
                    const maxIndex = Math.max(0, list.length - 1);
                    if (event.key === 'ArrowDown') {
                        event.preventDefault();
                        this.autocompleteIndex = Math.min(this.autocompleteIndex + 1, maxIndex);
                    } else if (event.key === 'ArrowUp') {
                        event.preventDefault();
                        this.autocompleteIndex = Math.max(this.autocompleteIndex - 1, 0);
                    } else if (event.key === 'Enter') {
                        event.preventDefault();
                        const item = list[this.autocompleteIndex];
                        if (item) {
                            const name = this.autocompleteType === 'tag'      ? item.tag_name
                                       : this.autocompleteType === 'location' ? item
                                       : item.name;
                            this.selectAutocomplete(name, item.id);
                        }
                    } else if (event.key === 'Escape') {
                        event.preventDefault();
                        this.showAutocomplete = false;
                    }
                } else if (event.key === 'Escape') {
                    this.switchToCreate();
                }
            },

            // value and cursorPos are captured at call time so the timer only
            // needs to do the fetch — no stale DOM references inside the closure.
            schedulePreview(value, cursorPos = null) {
                clearTimeout(this.previewTimer);
                if (!value || !value.trim() || !window.taskPreviewUrl) {
                    this.preview = null;
                    return;
                }

                // In multi-line mode, restrict the preview to the line the cursor is on.
                let previewValue = value;
                const nonEmptyLines = value.split('\n').filter(l => l.trim().length > 0);
                if (nonEmptyLines.length > 1 && cursorPos !== null) {
                    const lineStart = value.lastIndexOf('\n', cursorPos - 1) + 1;
                    const lineEnd   = value.indexOf('\n', cursorPos);
                    const line      = lineEnd === -1
                        ? value.substring(lineStart)
                        : value.substring(lineStart, lineEnd);
                    // Fall back to first non-empty line if cursor is on a blank line
                    previewValue = line.trim() ? line : nonEmptyLines[0];
                }

                this.previewTimer = setTimeout(async () => {
                    try {
                        const fd = new FormData();
                        fd.append('name', previewValue);
                        fd.append('_token', document.querySelector('meta[name="csrf-token"]').content);
                        const res = await fetch(window.taskPreviewUrl, { method: 'POST', body: fd });
                        if (res.ok) {
                            const data = await res.json();
                            this.preview = data;
                            if (data.projects) this.projects = data.projects;
                        }
                    } catch {}
                }, 400);
            },

            handleKeydown(event) {
                if (this.showAutocomplete) {
                    const list = this.autocompleteType === 'project'  ? this.filteredProjects
                               : this.autocompleteType === 'tag'      ? this.filteredTags
                               : this.autocompleteType === 'location' ? this.filteredLocations
                               : this.filteredUsers;
                    const maxIndex = Math.max(0, list.length - 1);

                    if (event.key === 'ArrowDown') {
                        event.preventDefault();
                        this.autocompleteIndex = Math.min(this.autocompleteIndex + 1, maxIndex);
                    } else if (event.key === 'ArrowUp') {
                        event.preventDefault();
                        this.autocompleteIndex = Math.max(this.autocompleteIndex - 1, 0);
                    } else if (event.key === 'Enter') {
                        event.preventDefault();
                        const item = list[this.autocompleteIndex];
                        if (item) {
                            const name = this.autocompleteType === 'tag'      ? item.tag_name
                                       : this.autocompleteType === 'location' ? item  // string, not object
                                       : item.name;
                            this.selectAutocomplete(name, item.id);
                        }
                    } else if (event.key === 'Escape') {
                        event.preventDefault();
                        this.showAutocomplete = false;
                    }
                } else if (event.key === 'Enter' && !event.shiftKey) {
                    // Enter without Shift submits; Shift+Enter inserts a newline (textarea default).
                    event.preventDefault();
                    this.validateAndSubmit({ target: event.target.closest('form') });
                } else if (event.key === 'ArrowUp' || event.key === 'ArrowDown') {
                    // Arrow keys move the cursor between lines — wait for the browser to
                    // reposition the cursor before sampling it.
                    this.$nextTick(() => {
                        const t = event.target;
                        this.schedulePreview(t.value, t.selectionStart);
                    });
                }
            },

            selectAutocomplete(name, id = null) {
                const isFilter = this.mode === 'filter';
                const inputEl  = isFilter ? this.$refs.filterInput : this.$refs.createInput;
                if (!inputEl) return;

                let slug, prefix, suffix = ' ';
                if (this.autocompleteType === 'project') {
                    slug   = name.toLowerCase().replace(/\s+/g, '-').replace(/[^a-z0-9-]/g, '');
                    prefix = '#';
                } else if (this.autocompleteType === 'tag') {
                    slug   = name.toLowerCase().replace(/\s+/g, '-').replace(/[^a-z0-9-]/g, '');
                    prefix = '@';
                } else if (this.autocompleteType === 'location') {
                    if (!isFilter && this.autocompleteLocationQuoted) {
                        // Quoted form (create mode only): keep exact name, wrap in "..."
                        slug   = name;
                        prefix = this.autocompleteLocationMap ? '++"' : '+"';
                        suffix = '" ';
                    } else {
                        // Unquoted form: lowercase, spaces → hyphens
                        slug   = name.toLowerCase().replace(/\s+/g, '-').replace(/[^a-z0-9-]/g, '');
                        prefix = (!isFilter && this.autocompleteLocationMap) ? '++' : '+';
                    }
                } else {
                    // user: lowercase, spaces removed
                    slug   = name.toLowerCase().replace(/\s+/g, '').replace(/[^a-z0-9]/g, '');
                    prefix = '&';
                }

                // In filter mode, prepend not: when the suggestion was triggered by not: prefix.
                const notStr       = isFilter && this.autocompleteNot ? 'not:' : '';
                const partialToken = notStr + prefix + this.autocompleteQuery;
                const currentValue = inputEl.value;
                // Case-insensitive position search so Not:#foo matches not:#foo
                const insertIdx    = currentValue.toLowerCase().lastIndexOf(partialToken.toLowerCase());
                let newCursorPos   = currentValue.length;
                if (insertIdx >= 0) {
                    const replacement = notStr + prefix + slug + suffix;
                    inputEl.value = currentValue.substring(0, insertIdx)
                        + replacement
                        + currentValue.substring(insertIdx + partialToken.length);
                    newCursorPos = insertIdx + replacement.length;
                }

                if (!isFilter) {
                    // Inject the ID directly into the form so the server doesn't rely on
                    // slug matching (which fails when the name contains spaces or special chars).
                    if (this.autocompleteType === 'tag' && id) {
                        const form = inputEl.closest('form');
                        if (form && !form.querySelector(`input[data-tag-slug="${slug}"]`)) {
                            const hidden = document.createElement('input');
                            hidden.type = 'hidden';
                            hidden.name = 'tag_ids[]';
                            hidden.value = id;
                            hidden.dataset.tagSlug = slug;
                            form.appendChild(hidden);
                        }
                    }
                    if (this.autocompleteType === 'project' && id) {
                        const form = inputEl.closest('form');
                        const prev = form.querySelector('input[data-project-autocomplete]');
                        if (prev) prev.remove();
                        const hidden = document.createElement('input');
                        hidden.type = 'hidden';
                        hidden.name = 'project_id';
                        hidden.value = id;
                        hidden.dataset.projectSlug = slug;
                        hidden.dataset.projectAutocomplete = '1';
                        form.appendChild(hidden);
                    }
                }

                this.showAutocomplete = false;
                this.$nextTick(() => {
                    inputEl.focus();
                    inputEl.setSelectionRange(newCursorPos, newCursorPos);
                    if (isFilter) {
                        this.query = inputEl.value;
                        this.filterTasks();
                    } else {
                        this.schedulePreview(inputEl.value, newCursorPos);
                    }
                });
            },

            init() {
                this.$nextTick(() => {
                    // Display any error message carried over from a partial bulk quick-add
                    const bulkErr = sessionStorage.getItem('quickAddBulkError');
                    if (bulkErr) {
                        sessionStorage.removeItem('quickAddBulkError');
                        this.serverError = bulkErr;
                    }

                    const container = this.$refs.taskContainer;
                    if (container) {
                        const total = container.querySelectorAll('[data-filterable]').length;
                        Alpine.store('taskCount').total = total;
                        Alpine.store('taskCount').visible = total;
                        Alpine.store('taskCount').filtered = false;
                        Alpine.store('taskCount').ready = true;
                    }
                    // Make projects and tags available to the bulk edit bar
                    if (this.projects && this.projects.length > 0) {
                        Alpine.store('bulkEdit').projects = this.projects;
                    }
                    if (this.tags && this.tags.length > 0) {
                        Alpine.store('bulkEdit').tags = this.tags;
                    }

                    // Re-apply filter when more completed/archived tasks are lazy-loaded
                    window.addEventListener('completed-tasks-loaded', () => {
                        if (this.query) this.filterTasks();
                    });
                });
            },
            switchToFilter() {
                this.mode = 'filter';
                this.showAutocomplete = false;
                clearTimeout(this.previewTimer);
                this.preview = null;
                this.$nextTick(() => this.$refs.filterInput && this.$refs.filterInput.focus());
            },
            switchToCreate() {
                this.mode = 'create';
                if (this.query) {
                    this.query = '';
                    this.filterTasks();
                }
                this.$nextTick(() => this.$refs.createInput && this.$refs.createInput.focus());
            },
            clearFilter() {
                if (this.query) {
                    this.query = '';
                    this.filterTasks();
                }
            },
            filterTasks() {
                const container  = this.$refs.taskContainer;
                if (!container) return;
                const filterRoot = container.closest('[x-data]');
                const allTasks   = filterRoot.querySelectorAll('[data-filterable]');
                const rawQuery   = this.query.trim().toLowerCase();

                if (!rawQuery) {
                    allTasks.forEach(el => el.style.display = '');
                    this.noResults = false;
                    Alpine.store('taskCount').visible  = container.querySelectorAll('[data-filterable]').length;
                    Alpine.store('taskCount').filtered = false;
                    window.dispatchEvent(new CustomEvent('filter-updated'));
                    return;
                }

                // Tokenize: handle not:"quoted term" and "quoted term" as single tokens.
                const tokenRegex        = /not:"([^"]+)"|"([^"]+)"|(\S+)/g;
                const projectFilters    = [], tagFilters     = [], nameFilters     = [],
                      locationFilters   = [], userFilters    = [],
                      notProjectFilters = [], notTagFilters  = [], notNameFilters  = [],
                      notLocationFilters= [], notUserFilters = [];
                let m;
                while ((m = tokenRegex.exec(rawQuery)) !== null) {
                    if (m[1] !== undefined) {
                        notNameFilters.push(m[1]);               // not:"quoted"
                    } else if (m[2] !== undefined) {
                        nameFilters.push(m[2]);                  // "quoted"
                    } else {
                        const token = m[3];
                        const isNot = token.startsWith('not:');
                        const val   = isNot ? token.substring(4) : token;
                        if (!val) continue;
                        if      (val.startsWith('#') && val.length > 1) (isNot ? notProjectFilters  : projectFilters ).push(val.substring(1));
                        else if (val.startsWith('@') && val.length > 1) (isNot ? notTagFilters      : tagFilters     ).push(val.substring(1));
                        else if (val.startsWith('+') && val.length > 1) (isNot ? notLocationFilters : locationFilters).push(val.substring(1));
                        else if (val.startsWith('&') && val.length > 1) (isNot ? notUserFilters     : userFilters    ).push(val.substring(1));
                        else                                             (isNot ? notNameFilters     : nameFilters    ).push(val);
                    }
                }

                let visibleCount = 0;
                allTasks.forEach(el => {
                    const taskName  = el.dataset.taskName  || '';
                    const project   = el.dataset.project   || '';
                    const tags      = el.dataset.tags      ? el.dataset.tags.split('|').filter(Boolean)      : [];
                    const location  = el.dataset.location  || '';
                    const assignees = el.dataset.assignees ? el.dataset.assignees.split('|').filter(Boolean) : [];

                    let ok = true;
                    // Positive filters — all must match
                    if (ok) for (const f of nameFilters)         { if (!taskName.includes(f))                           { ok = false; break; } }
                    if (ok) for (const f of projectFilters)      { if (!project.includes(f.replace(/-/g, ' ')))             { ok = false; break; } }
                    if (ok) for (const f of tagFilters)          { if (!tags.some(t => t.includes(f.replace(/-/g, ' '))))   { ok = false; break; } }
                    if (ok) for (const f of locationFilters)     { if (!location.includes(f.replace(/-/g, ' ')))        { ok = false; break; } }
                    if (ok) for (const f of userFilters)         { if (!assignees.some(a => a.includes(f)))             { ok = false; break; } }
                    // Negative filters — none may match
                    if (ok) for (const f of notNameFilters)      { if (taskName.includes(f))                            { ok = false; break; } }
                    if (ok) for (const f of notProjectFilters)   { if (project.includes(f.replace(/-/g, ' ')))          { ok = false; break; } }
                    if (ok) for (const f of notTagFilters)       { if (tags.some(t => t.includes(f.replace(/-/g, ' '))))    { ok = false; break; } }
                    if (ok) for (const f of notLocationFilters)  { if (location.includes(f.replace(/-/g, ' ')))         { ok = false; break; } }
                    if (ok) for (const f of notUserFilters)      { if (assignees.some(a => a.includes(f)))              { ok = false; break; } }

                    el.style.display = ok ? '' : 'none';
                    if (ok && container.contains(el)) visibleCount++;
                });

                this.noResults = visibleCount === 0 && container.querySelectorAll('[data-filterable]').length > 0;
                Alpine.store('taskCount').visible  = visibleCount;
                Alpine.store('taskCount').filtered = true;
                window.dispatchEvent(new CustomEvent('filter-updated'));
            }
        }
    };

    // Live-update list rows when the side panel saves a field
    window.addEventListener('task-panel-updated', (e) => {
        const d = e.detail;
        const group = document.querySelector(`[data-task-group-id="${d.id}"]`);
        if (!group) return;

        // Inactive (done/archived) AND the status field was just changed: fade the row out
        if (d.inactive && d.updated_field === 'status') {
            group.style.transition = 'opacity 0.4s';
            group.style.opacity = '0';
            setTimeout(() => { group.style.display = 'none'; }, 400);
            return;
        }

        // Task moved off this day view: fade it out
        const list = group.closest('[data-view-date]');
        if (list && d.date !== list.dataset.viewDate) {
            group.style.transition = 'opacity 0.4s';
            group.style.opacity = '0';
            setTimeout(() => { group.style.display = 'none'; }, 400);
            return;
        }

        // Name
        const nameEl = group.querySelector('[data-task-name-display]');
        if (nameEl) {
            nameEl.textContent = d.name;
            const row = group.querySelector('[data-filterable]');
            if (row) row.dataset.taskName = d.name.toLowerCase();
        }

        // Description preview
        const descEl = group.querySelector('[data-task-desc-display]');
        if (descEl) {
            if (d.description) {
                descEl.textContent = d.description.length > 100 ? d.description.substring(0, 97) + '...' : d.description;
                descEl.style.display = '';
            } else {
                descEl.style.display = 'none';
            }
        }

        // Date + time (full date view)
        const dateEl = group.querySelector('[data-task-date-display]');
        if (dateEl) {
            if (d.date_formatted) {
                dateEl.innerHTML = d.date_formatted +
                    (d.time_formatted ? ` <span class="text-gray-400">${d.time_formatted}</span>` : '');
                dateEl.style.display = '';
            } else {
                dateEl.style.display = 'none';
            }
        }

        // Time-only (hideDate views like day agenda)
        const timeEl = group.querySelector('[data-task-time-display]');
        if (timeEl) {
            if (d.time_formatted) {
                timeEl.textContent = d.time_formatted;
                timeEl.style.display = '';
            } else {
                timeEl.style.display = 'none';
            }
        }

        // Project
        const projEl = group.querySelector('[data-task-project-display]');
        if (projEl) {
            if (d.project_name) {
                projEl.textContent = d.project_name;
                projEl.style.display = '';
            } else {
                projEl.style.display = 'none';
            }
        }

        // Recurrence
        const recEl = group.querySelector('[data-task-recurrence-display]');
        if (recEl) {
            if (d.recurrence_pattern) {
                recEl.textContent = d.recurrence_pattern;
                recEl.style.display = '';
            } else {
                recEl.style.display = 'none';
            }
        }

        // Tags
        const tagsEl = group.querySelector('[data-task-tags-display]');
        if (tagsEl) {
            if (d.tags && d.tags.length > 0) {
                tagsEl.innerHTML = d.tags.map(t =>
                    `<span class="inline-block px-2 py-1 text-xs rounded"
                           style="background-color:${t.color}22;color:${t.color}">${t.name}</span>`
                ).join('');
                tagsEl.style.display = '';
                const row = group.querySelector('[data-filterable]');
                if (row) row.dataset.tags = d.tags.map(t => t.name.toLowerCase()).join('|');
            } else {
                tagsEl.innerHTML = '';
                tagsEl.style.display = 'none';
                const row = group.querySelector('[data-filterable]');
                if (row) row.dataset.tags = '';
            }
        }
    });
</script>
@endPushOnce

@if($depth === 0)
    @php
        $taskIds = $tasks->pluck('id')->flip()->all();
        $tasks = $tasks->filter(fn($t) => is_null($t->parent_id) || !isset($taskIds[$t->parent_id]));
    @endphp
@endif

@if($sortable && !$readOnly && $depth === 0)
<div class="space-y-2"
     x-data
     x-init="initTaskSortable($el)"
     @if($reorderUrl) data-reorder-url="{{ $reorderUrl }}" @endif
     @if($viewDate) data-view-date="{{ $viewDate }}" @endif>
@else
<div class="space-y-2" @if($viewDate) data-view-date="{{ $viewDate }}" @endif>
@endif
    @forelse($tasks as $task)
        @php
            // Find first image attachment from task or comments
            $imageAttachment = null;
            $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];

            // Check task attachments first
            foreach($task->attachments as $attachment) {
                $ext = strtolower(pathinfo($attachment->file_path, PATHINFO_EXTENSION));
                if (in_array($ext, $imageExtensions)) {
                    $imageAttachment = $attachment;
                    break;
                }
            }

            // If no task attachment image, check comment attachments
            if (!$imageAttachment) {
                foreach($task->comments as $comment) {
                    if ($comment->attachment_path) {
                        $ext = strtolower(pathinfo($comment->attachment_path, PATHINFO_EXTENSION));
                        if (in_array($ext, $imageExtensions)) {
                            $imageAttachment = $comment;
                            break;
                        }
                    }
                }
            }

            $marginLeft = $depth * 24; // 24px per level
        @endphp
        <div data-task-group data-task-group-id="{{ $task->id }}" x-data="{ subtasksOpen: true }">
        <div class="bg-[#202020] p-4 rounded-lg shadow hover:shadow-md transition border border-gray-700 group"
             data-filterable
             data-task-name="{{ strtolower($task->name) }}"
             data-project="{{ preg_replace('/[^a-z0-9 ]/', '', strtolower($task->project?->name ?? '')) }}"
             data-tags="{{ preg_replace('/[^a-z0-9 |]/', '', strtolower($task->tags->pluck('tag_name')->join('|'))) }}"
             data-location="{{ strtolower($task->location ?? '') }}"
             data-assignees="{{ $task->assignees->map(fn($a) => preg_replace('/[^a-z0-9]/', '', strtolower($a->name)))->join('|') }}"
             style="margin-left: {{ $marginLeft }}px;"
             :class="$store.bulkEdit.active && $store.bulkEdit.isSelected({{ $task->id }}) ? 'ring-2 ring-blue-500 ring-inset' : ''">
            <div class="flex items-start gap-4">
                <!-- Sort controls (drag handle + arrow buttons, custom sort mode only) -->
                @if($sortable && !$readOnly && $depth === 0)
                <div class="self-stretch flex items-center flex-shrink-0 gap-1"
                     :class="{ 'invisible pointer-events-none': $store.bulkEdit.active }"
                     @click.stop>
                    <!-- Arrow buttons: to-top, up, down, to-bottom -->
                    <div class="flex flex-col justify-center gap-0.5">
                        <button data-sort-top type="button"
                                @click.stop="taskMoveInList($el, 'top')"
                                title="Move to top"
                                class="text-gray-500 hover:text-gray-200 transition-colors rounded p-0.5 focus:outline-none">
                            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <polyline points="5,12 12,5 19,12"/>
                                <polyline points="5,18 12,11 19,18"/>
                            </svg>
                        </button>
                        <button data-sort-up type="button"
                                @click.stop="taskMoveInList($el, 'up')"
                                title="Move up"
                                class="text-gray-500 hover:text-gray-200 transition-colors rounded p-0.5 focus:outline-none">
                            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <polyline points="5,15 12,8 19,15"/>
                            </svg>
                        </button>
                        <button data-sort-down type="button"
                                @click.stop="taskMoveInList($el, 'down')"
                                title="Move down"
                                class="text-gray-500 hover:text-gray-200 transition-colors rounded p-0.5 focus:outline-none">
                            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <polyline points="5,9 12,16 19,9"/>
                            </svg>
                        </button>
                        <button data-sort-bottom type="button"
                                @click.stop="taskMoveInList($el, 'bottom')"
                                title="Move to bottom"
                                class="text-gray-500 hover:text-gray-200 transition-colors rounded p-0.5 focus:outline-none">
                            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <polyline points="5,6 12,13 19,6"/>
                                <polyline points="5,12 12,19 19,12"/>
                            </svg>
                        </button>
                    </div>
                    <!-- Drag handle -->
                    <div class="drag-handle touch-none flex items-center self-stretch"
                         style="cursor: grab"
                         title="Drag to reorder">
                        <svg class="w-4 h-4 text-gray-600" viewBox="0 0 16 24" fill="currentColor" aria-hidden="true">
                            <circle cx="5" cy="6" r="1.5"/><circle cx="11" cy="6" r="1.5"/>
                            <circle cx="5" cy="12" r="1.5"/><circle cx="11" cy="12" r="1.5"/>
                            <circle cx="5" cy="18" r="1.5"/><circle cx="11" cy="18" r="1.5"/>
                        </svg>
                    </div>
                </div>
                @endif

                <!-- Bulk edit: square selector (shown in bulk mode for all tasks) -->
                @if(!$readOnly)
                <button x-show="$store.bulkEdit.active"
                        @click.stop="$store.bulkEdit.toggleTask({{ $task->id }})"
                        title="Select task"
                        class="mt-1 w-6 h-6 flex-shrink-0 rounded border-2 flex items-center justify-center transition"
                        :class="$store.bulkEdit.isSelected({{ $task->id }})
                            ? 'border-blue-500 bg-blue-500'
                            : 'border-gray-500 hover:border-blue-400'">
                    <svg x-show="$store.bulkEdit.isSelected({{ $task->id }})"
                         class="w-3.5 h-3.5 text-white" fill="currentColor" viewBox="0 0 20 20" style="display:none">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                    </svg>
                </button>
                @endif

                <!-- Complete circle / quick-complete form (hidden in bulk mode) -->
                <div class="flex flex-col items-center flex-shrink-0">
                @if($showAsArchived)
                    <div class="mt-1 w-6 h-6 rounded-full bg-gray-600" title="Archived"></div>
                @elseif($task->status === 'done')
                    <div x-show="!$store.bulkEdit.active"
                         class="mt-1 w-6 h-6 rounded-full bg-green-600" title="Completed"></div>
                @elseif($task->status === 'archived')
                    <div x-show="!$store.bulkEdit.active"
                         class="mt-1 w-6 h-6 rounded-full bg-gray-600" title="Archived"></div>
                @elseif($readOnly)
                    <div class="mt-1 w-6 h-6 rounded-full border-2 border-gray-700" title="Project is inactive"></div>
                @else
                <form x-show="!$store.bulkEdit.active"
                      x-data="listQuickComplete()" @submit.prevent="submit()"
                      @undo-complete.stop="done = false"
                      method="POST" action="{{ route('tasks.update', $task) }}" onclick="event.stopPropagation()"
                      data-task-id="{{ $task->id }}"
                      data-task-name="{{ $task->name }}"
                      data-undo-url="{{ route('tasks.updateField', $task) }}"
                      data-recurring="{{ $task->recurrence_pattern ? 'true' : 'false' }}">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="status" value="done">
                    <input type="hidden" name="name" value="{{ $task->name }}">
                    <input type="hidden" name="description" value="{{ $task->description }}">
                    <input type="hidden" name="location" value="{{ $task->location }}">
                    <input type="hidden" name="date" value="{{ $task->date }}">
                    <input type="hidden" name="time" value="{{ $task->time }}">
                    <input type="hidden" name="project_id" value="{{ $task->project_id }}">
                    <input type="hidden" name="parent_id" value="{{ $task->parent_id }}">
                    <input type="hidden" name="recurrence_pattern" value="{{ $task->recurrence_pattern }}">
                    @foreach($task->tags as $tag)
                        <input type="hidden" name="tag_ids[]" value="{{ $tag->id }}">
                    @endforeach
                    @foreach($task->assignees as $assignee)
                        <input type="hidden" name="assignee_ids[]" value="{{ $assignee->id }}">
                    @endforeach
                    <input type="hidden" name="quick_complete" value="1">
                    @php
                        $buttonClass = 'mt-1 w-6 h-6 rounded-full border-2 transition flex-shrink-0';
                        $titleText = 'Mark as done';

                        if ($task->recurrence_pattern && $task->children->count() > 0) {
                            $buttonClass .= ' border-purple-400 hover:border-purple-500';
                            $titleText = 'Complete & create next with ' . $task->children->count() . ' subtask(s) (' . $task->recurrence_pattern . ')';
                        } elseif ($task->recurrence_pattern) {
                            $buttonClass .= ' border-purple-400 hover:border-purple-500';
                            $titleText = 'Complete & create next (' . $task->recurrence_pattern . ')';
                        } elseif ($task->children->count() > 0) {
                            $buttonClass .= ' border-blue-400 hover:border-blue-500';
                            $titleText = 'Complete with ' . $task->children->count() . ' subtask(s)';
                        } else {
                            $buttonClass .= ' border-gray-400 hover:border-green-400';
                        }

                        $buttonClass .= ' hover:bg-green-400 hover:bg-opacity-20';
                    @endphp
                    <button x-show="!done" type="submit"
                            :disabled="loading" :class="loading ? 'opacity-40 cursor-wait' : ''"
                            class="{{ $buttonClass }}"
                            title="{{ $titleText }}">
                    </button>
                    <div x-show="done" class="mt-1 w-6 h-6 rounded-full bg-green-600 flex-shrink-0" style="display:none"></div>
                    <div x-show="error" x-text="error" class="mt-1 text-xs text-red-400 max-w-xs" style="display:none"></div>
                </form>
                @endif
                    <button x-data="{ copied: false }"
                            @click.prevent.stop="navigator.clipboard.writeText('{{ route('tasks.show', $task) }}'); copied = true; setTimeout(() => copied = false, 1500)"
                            title="Copy link to this task"
                            class="text-[10px] leading-none mt-1 transition-colors cursor-pointer"
                            :class="copied ? 'text-green-500' : 'text-gray-600 hover:text-gray-400'">
                        <span x-show="!copied">#{{ $task->id }}</span>
                        <span x-show="copied" style="display:none">✓</span>
                    </button>
                </div>

                <!-- Task Content -->
                <div class="flex-1 min-w-0"
                     :class="$store.bulkEdit.active ? 'cursor-pointer' : 'cursor-pointer'"
                     @click="$store.bulkEdit.active
                         ? $store.bulkEdit.toggleTask({{ $task->id }})
                         : ((event.ctrlKey || event.metaKey) ? window.open('{{ route('tasks.show', $task) }}', '_blank') : openTaskPanel({{ $task->id }}))"
                     title="Click to peek · Ctrl+click or middle-click to open full page">
                    <!-- Show parent context if exists -->
                    @if($task->parent)
                        <div class="text-xs text-gray-500 mb-1">
                            <span class="text-gray-600">↳ Subtask of:</span>
                            <a href="{{ route('tasks.show', $task->parent) }}"
                               class="text-blue-400 hover:underline"
                               onclick="event.stopPropagation()">
                                {{ $task->parent->name }}
                            </a>
                        </div>
                    @endif

                    <h3 class="font-semibold {{ ($showAsArchived || $task->status === 'archived') ? 'text-gray-500 line-through' : 'text-gray-100' }}">
                        <span data-task-name-display class="task-title">{!! render_title($task->name) !!}</span>
                        @if($task->children->count() > 0)
                            <span class="text-xs text-gray-500 font-normal">
                                (<span x-show="!subtasksOpen" style="display:none">{{ $task->children->count() }}</span><span x-show="subtasksOpen">{{ $task->incompleteChildren()->count() }}/{{ $task->children->count() }}</span> subtasks)
                                <button @click.stop="subtasksOpen = !subtasksOpen"
                                        class="inline-flex items-center justify-center w-4 h-4 ml-0.5 rounded text-gray-500 hover:text-gray-300 hover:bg-gray-700 transition"
                                        :title="subtasksOpen ? 'Collapse subtasks' : 'Expand subtasks'">
                                    <svg x-show="subtasksOpen" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M20 12H4"/></svg>
                                    <svg x-show="!subtasksOpen" class="w-3 h-3" style="display:none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                                </button>
                            </span>
                        @endif
                        @php $rescheduleCount = $task->rescheduleCount(); @endphp
                        @if($rescheduleCount >= config('app.reschedule_badge_threshold'))
                            <span class="ml-3 inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-amber-900 bg-opacity-40 text-amber-400 border border-amber-700"
                                  title="Rescheduled {{ $rescheduleCount }} time{{ $rescheduleCount === 1 ? '' : 's' }}">
                                ↻ {{ $rescheduleCount }}
                            </span>
                        @endif
                    </h3>
                    <p class="text-sm text-gray-400 mt-1 break-words" data-task-desc-display
                       @unless($task->description) style="display:none" @endunless>{{ Str::limit($task->description ?? '', 100) }}</p>
                    <div class="flex items-center gap-3 mt-2 text-xs text-gray-500">
                        @if($hideDate)
                            @if($task->time)
                                <span class="text-gray-400" data-task-time-display>{{ \Carbon\Carbon::parse($task->time)->format('g:i A') }}</span>
                            @endif
                        @elseif($task->date)
                            <span data-task-date-display>
                                {{ \Carbon\Carbon::parse($task->date)->format('l, F j, Y') }}
                                @if($task->time)
                                    <span class="text-gray-400">{{ \Carbon\Carbon::parse($task->time)->format('g:i A') }}</span>
                                @endif
                            </span>
                        @endif
                        @if($task->project)
                            <a href="{{ route('projects.show', $task->project) }}"
                               onclick="event.stopPropagation()"
                               class="text-blue-400 hover:text-blue-300 hover:underline"
                               data-task-project-display>{{ $task->project->name }}</a>
                        @endif
                        @if($task->recurrence_pattern)
                            <span class="text-purple-400" data-task-recurrence-display>{{ $task->recurrence_pattern }}</span>
                        @endif
                        @if($task->location)
                            @php
                                $truncLen = config('taskfiend.location_truncate_length', 30);
                                $locDisplay = ($truncLen > 0 && mb_strlen($task->location) > $truncLen)
                                    ? mb_substr($task->location, 0, $truncLen) . '…'
                                    : $task->location;
                            @endphp
                            @if($task->show_map)
                                @php $mapUrl = sprintf(config('taskfiend.maps_url_template', 'https://maps.google.com/?q=%s'), urlencode($task->location)); @endphp
                                <a href="{{ $mapUrl }}" target="_blank" rel="noopener"
                                   onclick="event.stopPropagation()"
                                   title="{{ $task->location }}"
                                   class="inline-flex items-center gap-0.5 border border-orange-400 text-orange-400 rounded px-1.5 py-0.5 hover:bg-orange-400/10 hover:underline"
                                   data-task-location-display>{{ $locDisplay }}<svg class="inline w-2.5 h-2.5 opacity-70 flex-shrink-0" viewBox="0 0 10 10" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M1 9L9 1M9 1H4M9 1V6"/></svg></a>
                            @else
                                <span class="border border-gray-400 text-gray-400 rounded px-1.5 py-0.5"
                                      title="{{ mb_strlen($task->location) > $truncLen && $truncLen > 0 ? $task->location : '' }}"
                                      data-task-location-display>{{ $locDisplay }}</span>
                            @endif
                        @endif
                        @if($task->description)
                            <span class="flex items-center gap-1 text-gray-500" title="Has description">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h10"></path>
                                </svg>
                            </span>
                        @endif
                        @if($task->comments->count() > 0)
                            <span class="flex items-center gap-1 text-gray-500" title="{{ $task->comments->count() }} comment(s)">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                                </svg>
                                {{ $task->comments->count() }}
                            </span>
                        @endif
                        @if($task->attachments->count() > 0)
                            <span class="flex items-center gap-1 text-gray-500" title="{{ $task->attachments->count() }} attachment(s)">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                                </svg>
                            </span>
                        @endif
                    </div>
                    <div class="flex gap-1 mt-2 flex-wrap" data-task-tags-display
                         @if($task->tags->count() === 0) style="display:none" @endif>
                        @foreach($task->tags as $tag)
                            <a href="{{ route('tags.show', $tag) }}"
                               onclick="event.stopPropagation()"
                               class="inline-block px-2 py-1 text-xs rounded hover:opacity-75"
                               style="background-color: {{ $tag->color }}22; color: {{ $tag->color }}">
                                {{ $tag->tag_name }}
                            </a>
                        @endforeach
                    </div>
                </div>

                <!-- Completer / Assignee Avatars -->
                @php
                    $avatarColors = ['bg-blue-500', 'bg-green-500', 'bg-yellow-500', 'bg-purple-500', 'bg-pink-500', 'bg-indigo-500', 'bg-red-500', 'bg-teal-500'];
                @endphp
                @if($task->status === 'done' && $task->completionLog?->user)
                    @php $completer = $task->completionLog->user; @endphp
                    <div class="flex-shrink-0">
                        @if($completer->profile_image)
                            <img src="{{ route('profile.image.show', $completer) }}"
                                 alt="{{ $completer->name }}"
                                 title="Completed by {{ $completer->name }}"
                                 class="w-8 h-8 rounded-full object-cover shadow-sm ring-2 ring-green-600">
                        @else
                            <div class="w-8 h-8 rounded-full {{ $avatarColors[$completer->id % count($avatarColors)] }} flex items-center justify-center text-xs font-bold text-white shadow-sm ring-2 ring-green-600"
                                 title="Completed by {{ $completer->name }}">
                                {{ strtoupper(substr($completer->name, 0, 1)) }}
                            </div>
                        @endif
                    </div>
                @elseif($task->assignees->count() > 0)
                    <div class="flex-shrink-0 flex space-x-1">
                        @foreach($task->assignees->take(3) as $assignee)
                            @if($assignee->profile_image)
                                <img src="{{ route('profile.image.show', $assignee) }}"
                                     alt="{{ $assignee->name }}"
                                     title="{{ $assignee->name }}"
                                     class="w-8 h-8 rounded-full object-cover shadow-sm">
                            @else
                                <div class="w-8 h-8 rounded-full {{ $avatarColors[$assignee->id % count($avatarColors)] }} flex items-center justify-center text-xs font-bold text-white shadow-sm"
                                     title="{{ $assignee->name }}">
                                    {{ strtoupper(substr($assignee->name, 0, 1)) }}
                                </div>
                            @endif
                        @endforeach
                        @if($task->assignees->count() > 3)
                            <div class="w-8 h-8 rounded-full bg-gray-600 flex items-center justify-center text-xs font-medium text-gray-300 shadow-sm"
                                 title="{{ $task->assignees->count() - 3 }} more">
                                +{{ $task->assignees->count() - 3 }}
                            </div>
                        @endif
                    </div>
                @endif

                <!-- Image Thumbnail -->
                @if($imageAttachment)
                    <div class="flex-shrink-0">
                        @if($imageAttachment instanceof \App\Models\TaskAttachment)
                            <img src="{{ route('attachments.download', [$task, $imageAttachment]) }}"
                                 alt="Attachment"
                                 class="w-16 h-16 object-cover rounded border border-gray-600">
                        @else
                            <img src="{{ Storage::url($imageAttachment->attachment_path) }}"
                                 alt="Comment attachment"
                                 class="w-16 h-16 object-cover rounded border border-gray-600">
                        @endif
                    </div>
                @endif
            </div>
        </div>

        <!-- Recursively render subtasks -->
        @if($task->children->count() > 0)
            <div x-show="subtasksOpen" x-transition:leave="transition-opacity duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
                <x-task-list :tasks="$task->children" :depth="$depth + 1" :hide-date="false" :read-only="$readOnly" />
            </div>
        @endif
        </div>{{-- /data-task-group --}}
    @empty
        <div class="bg-[#202020] p-8 rounded-lg text-center text-gray-400 border border-gray-700">
            No tasks found.
        </div>
    @endforelse
</div>
