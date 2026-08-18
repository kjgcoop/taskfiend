import './bootstrap';
import Alpine from '@alpinejs/csp';

window.Alpine = Alpine;

// Shared micro-components registered before Alpine.start()
Alpine.data('dropdown', () => ({ open: false }));
Alpine.data('copyButton', () => ({ copied: false }));
Alpine.data('flashMessage', () => ({ show: true }));
Alpine.data('subtaskGroup', () => ({ subtasksOpen: true }));
// Drag/arrow reordering for a sibling list of task cards. Registered globally (rather than
// locally in task-list.blade.php's @pushOnce) so subtask-list.blade.php can use it too without
// depending on task-list.blade.php also being on the page. window.initTaskSortable/taskMoveInList
// live in layouts/app.blade.php, loaded on every page.
//
// taskMoveInList is exposed here (delegating to the window one) rather than left as a bare
// window global, because Alpine's CSP-safe evaluator does NOT fall back to window/globalThis for
// an identifier a directive references — unlike normal Alpine (which evaluates expressions with
// `new Function` + `with`, where an unresolved identifier naturally falls through to the global
// scope), the CSP build's hand-rolled expression interpreter only resolves identifiers found in
// the Alpine scope stack (magics + x-data components, walking up through ancestors) and throws
// "Undefined variable: X" for anything else. Every @click="taskMoveInList($el, ...)" needs a
// real ancestor x-data exposing that name, not just a same-named window function — the arrow
// buttons on the main task list happened to work only because they sit inside taskFilter's
// x-data, which (redundantly) defined its own copy of this same method; subtask-list.blade.php's
// arrow buttons have no such ancestor, so they threw. Defining it here means any container using
// x-data="taskSortableList" gets working arrow buttons regardless of what else wraps it.
Alpine.data('taskSortableList', () => ({
    init() { window.initTaskSortable(this.$el); },
    taskMoveInList(el, direction) { window.taskMoveInList(el, direction); },
}));
// Quick-complete circle button on a task-list row. Same story as taskSortableList above: this
// used to live only in task-list.blade.php's @pushOnce, which subtask-list.blade.php relied on
// without actually including — so the quick-complete button on subtask rows silently threw
// "Undefined variable: listQuickComplete" (and the "loading"/"done" refs inside it) on any page
// that renders <x-subtask-list> without <x-task-list> also being present, e.g. the task show
// page and its sidebar panel (tasks/show.blade.php). Registered globally instead.
Alpine.data('listQuickComplete', function () {
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
                            taskId:     form.dataset.taskId,
                            taskName:   form.dataset.taskName,
                            undoUrl:    form.dataset.undoUrl,
                            recurring:  form.dataset.recurring === 'true',
                            nextTaskId: data.next_task_id ?? null,
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
});
Alpine.data('tabSwitcher', () => ({ tab: 'comments' }));
Alpine.data('uploadToggle', () => ({
    showUpload: false,
    toggle() { this.showUpload = !this.showUpload; },
    hide() { this.showUpload = false; },
}));
Alpine.data('passwordToggle', () => ({ showPassword: false }));
Alpine.data('fileInput', () => ({ fileName: '' }));
Alpine.data('multiFileInput', () => ({
    fileName: '',
    updateLabel(files) {
        if (!files || files.length === 0) { this.fileName = ''; return; }
        this.fileName = files.length === 1 ? files[0].name : `${files.length} files selected`;
    },
}));
Alpine.data('templateItem', () => ({ showUse: false, showDelete: false }));
Alpine.data('templateNameEditor', function () {
    return {
        templateId: 0,
        name: '',
        original: '',
        editing: false,

        init() {
            this.templateId = parseInt(this.$el.dataset.templateId);
            this.name = this.$el.dataset.templateName || '';
            this.original = this.name;
        },

        startEdit() {
            this.original = this.name;
            this.editing = true;
            // $nextTick only waits for Alpine's DOM patch (a microtask), not for the browser to
            // actually lay out/paint the now-visible element — focusing before that paint can
            // silently fail to seat the cursor until a second, later tap. See
            // taskPanelEditor.startEdit() in resources/views/layouts/app.blade.php for the fuller
            // writeup; wrapping in requestAnimationFrame after nextTick fixes it here too.
            this.$nextTick(() => requestAnimationFrame(() => {
                if (this.$refs.nameInput) {
                    this.$refs.nameInput.focus();
                    this.$refs.nameInput.select();
                }
            }));
        },

        cancel() {
            this.name = this.original;
            this.editing = false;
        },

        async save() {
            if (this.name.trim() === this.original.trim()) {
                this.editing = false;
                return;
            }
            try {
                const resp = await fetch(`/templates/${this.templateId}`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ name: this.name.trim() }),
                });
                const data = await resp.json();
                if (data.success) {
                    this.name = data.name;
                    this.original = data.name;
                    this.editing = false;
                    window.location.reload();
                } else {
                    alert(data.message || 'Failed to save');
                    this.name = this.original;
                    this.editing = false;
                }
            } catch (e) {
                alert('An error occurred while saving');
                this.name = this.original;
                this.editing = false;
            }
        },
    };
});
Alpine.data('projectsMenu', () => ({
    open: false,
    openImportTemplate() {
        this.open = false;
        window.dispatchEvent(new CustomEvent('do-toggle-import-template'));
    },
    openMarkdownImport() {
        this.open = false;
        window.dispatchEvent(new CustomEvent('do-toggle-markdown-import'));
    },
}));
Alpine.data('templateDatePicker', () => ({
    dateInput: '',
    datePreview: '',
    isFuture: false,
    async previewDate() {
        const val = this.dateInput.trim();
        if (!val) { this.datePreview = ''; this.isFuture = false; return; }
        try {
            const resp = await fetch('/tasks/parse-date', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ input: val }),
            });
            const data = await resp.json();
            if (data.date) {
                this.datePreview = data.formatted;
                const today = new Date().toISOString().slice(0, 10);
                this.isFuture = data.date > today;
            } else {
                this.datePreview = '';
                this.isFuture = false;
            }
        } catch { this.datePreview = ''; this.isFuture = false; }
    },
}));
Alpine.data('colorPicker', () => ({
    selected: '#3B82F6',
    init() { if (this.$el.dataset.color) this.selected = this.$el.dataset.color; },
}));
Alpine.data('reviewTaskRow', () => ({
    done: false,
    loading: false,
    async submit() {
        this.loading = true;
        try {
            const res = await fetch(this.$el.action, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: new FormData(this.$el),
            });
            const data = await res.json().catch(() => ({}));
            if (res.ok && data.ok !== false) {
                this.done = true;
                await new Promise(r => setTimeout(r, 400));
                const row = this.$el.closest('[data-review-row]');
                if (row) {
                    row.style.transition = 'opacity 0.3s';
                    row.style.opacity = '0';
                    setTimeout(() => row.style.display = 'none', 300);
                }
            } else {
                alert('Could not complete task: ' + (data.message || 'Please try again.'));
            }
        } catch { this.$el.submit(); }
        finally { this.loading = false; }
    },
}));

Alpine.start();
