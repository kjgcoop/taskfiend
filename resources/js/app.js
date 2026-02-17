import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

window.taskFilter = function () {
    return {
        query: '',
        noResults: false,
        filterTasks() {
            const container = this.$refs.taskContainer;
            const tasks = container.querySelectorAll('[data-filterable]');
            const query = this.query.trim().toLowerCase();

            if (!query) {
                tasks.forEach(el => el.style.display = '');
                this.noResults = false;
                return;
            }

            const tokens = query.split(/\s+/).filter(t => t.length > 0);
            const projectFilters = [];
            const tagFilters = [];
            const nameFilters = [];

            tokens.forEach(token => {
                if (token.startsWith('#') && token.length > 1) {
                    projectFilters.push(token.substring(1));
                } else if (token.startsWith('@') && token.length > 1) {
                    tagFilters.push(token.substring(1));
                } else {
                    nameFilters.push(token);
                }
            });

            let visibleCount = 0;

            tasks.forEach(el => {
                const taskName = el.dataset.taskName || '';
                const projectName = el.dataset.project || '';
                const tagList = el.dataset.tags ? el.dataset.tags.split('|') : [];

                let matches = true;

                for (const filter of nameFilters) {
                    if (!taskName.includes(filter)) {
                        matches = false;
                        break;
                    }
                }

                if (matches) {
                    for (const filter of projectFilters) {
                        if (!projectName.includes(filter)) {
                            matches = false;
                            break;
                        }
                    }
                }

                if (matches) {
                    for (const filter of tagFilters) {
                        if (!tagList.some(tag => tag.includes(filter))) {
                            matches = false;
                            break;
                        }
                    }
                }

                el.style.display = matches ? '' : 'none';
                if (matches) visibleCount++;
            });

            this.noResults = visibleCount === 0 && tasks.length > 0;
        }
    }
};

Alpine.start();
