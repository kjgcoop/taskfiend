import './bootstrap';
import Alpine from '@alpinejs/csp';

window.Alpine = Alpine;

// Shared micro-components registered before Alpine.start()
Alpine.data('dropdown', () => ({ open: false }));
Alpine.data('copyButton', () => ({ copied: false }));
Alpine.data('flashMessage', () => ({ show: true }));
Alpine.data('subtaskGroup', () => ({ subtasksOpen: true }));
Alpine.data('tabSwitcher', () => ({ tab: 'comments' }));

Alpine.start();
