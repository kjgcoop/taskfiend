<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div class="flex items-center gap-3">
                <h2 class="font-semibold text-xl text-gray-100 leading-tight">
                    {{ __('Today') }} - {{ now()->format('l, F j, Y') }}
                    <span class="text-sm text-gray-500 font-normal">{{ $tasks->count() }}</span>
                </h2>
                <a href="{{ route('day') }}?date={{ now()->addDay()->format('Y-m-d') }}" class="text-gray-400 hover:text-gray-100" title="Tomorrow">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                </a>
            </div>
            <a href="{{ route('tasks.create') }}?date={{ now()->format('Y-m-d') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700">
                Add Task
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8" x-data="taskFilter()">
            <div class="mb-4">
                <input type="text"
                       x-model="query"
                       @input="filterTasks()"
                       placeholder="Filter tasks... (# project, @ tag)"
                       class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-md text-gray-100 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>
            <div x-ref="taskContainer">
                <x-task-list :tasks="$tasks" />
            </div>
            <div x-show="noResults" x-cloak class="bg-gray-800 p-8 rounded-lg text-center text-gray-400 border border-gray-700">
                No tasks match your filter.
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function taskFilter() {
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
        }
    </script>
    @endpush
</x-app-layout>
