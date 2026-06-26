import './bootstrap';
import Alpine from '@alpinejs/csp';

window.Alpine = Alpine;

// Shared micro-components registered before Alpine.start()
Alpine.data('dropdown', () => ({ open: false }));
Alpine.data('copyButton', () => ({ copied: false }));
Alpine.data('flashMessage', () => ({ show: true }));
Alpine.data('subtaskGroup', () => ({ subtasksOpen: true }));
Alpine.data('tabSwitcher', () => ({ tab: 'comments' }));
Alpine.data('uploadToggle', () => ({
    showUpload: false,
    toggle() { this.showUpload = !this.showUpload; },
    hide() { this.showUpload = false; },
}));
Alpine.data('passwordToggle', () => ({ showPassword: false }));
Alpine.data('fileInput', () => ({ fileName: '' }));
Alpine.data('templateItem', () => ({ showUse: false, showDelete: false }));
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
            if (data.formatted && data.date) {
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
