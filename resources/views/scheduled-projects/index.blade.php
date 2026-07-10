<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-100 leading-tight">
                Scheduled Projects
            </h2>
            <a href="{{ route('templates.index') }}" class="text-sm text-gray-400 hover:text-gray-200">
                &larr; Back to Templates
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if(session('status'))
                <div class="bg-green-900/40 border border-green-700 text-green-300 px-4 py-3 rounded">
                    {{ session('status') }}
                </div>
            @endif

            @if(session('error'))
                <div class="bg-red-900/40 border border-red-700 text-red-300 px-4 py-3 rounded">
                    {{ session('error') }}
                </div>
            @endif

            @if($scheduled->isEmpty())
                <div class="bg-[#202020] border border-gray-700 rounded-lg p-8 text-center text-gray-500">
                    No projects are scheduled. Use a template and pick a future date to schedule one.
                </div>
            @else
                <div class="bg-[#202020] border border-gray-700 rounded-lg divide-y divide-gray-700">
                    @foreach($scheduled as $item)
                        <div class="flex items-center justify-between gap-4 px-5 py-4">
                            <div class="flex-1 min-w-0"
                                 x-data="scheduledProjectNameEditor"
                                 data-scheduled-id="{{ $item->id }}"
                                 data-project-name="{{ $item->project_name }}">
                                <div class="text-gray-100 font-medium truncate">
                                    <span x-show="!editing"
                                          @click="startEdit()"
                                          class="cursor-pointer hover:text-gray-300"
                                          x-text="name"></span>
                                    <input x-show="editing" x-cloak x-ref="nameInput"
                                           x-model="name"
                                           @blur="save()"
                                           @keydown.enter.prevent="save()"
                                           @keydown.escape.prevent="cancel()"
                                           class="text-gray-100 font-medium bg-transparent border-b border-gray-400 focus:outline-none focus:border-blue-400 w-full max-w-xs" />
                                </div>
                                <div class="text-sm text-gray-400 mt-0.5">
                                    From template: <span class="text-gray-300">{{ $item->template?->name ?? 'Deleted template' }}</span>
                                </div>
                                <div x-show="error" x-cloak class="text-sm text-red-400 mt-0.5" x-text="error"></div>
                            </div>
                            <div class="text-sm text-gray-300 whitespace-nowrap">
                                {{ $item->start_date->format('l, M j, Y') }}
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <form method="POST" action="{{ route('scheduled-projects.create-now', $item) }}">
                                    @csrf
                                    <button type="submit"
                                            class="text-sm px-3 py-1.5 bg-gray-700 hover:bg-blue-900/60 text-gray-300 hover:text-blue-300 rounded border border-gray-600 hover:border-blue-700/50">
                                        Create Now
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('scheduled-projects.destroy', $item) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="text-sm px-3 py-1.5 bg-gray-700 hover:bg-red-900/60 text-gray-300 hover:text-red-300 rounded border border-gray-600 hover:border-red-700/50">
                                        Cancel
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

        </div>
    </div>

    @push('scripts')
    <script nonce="{{ csp_nonce() }}">
        document.addEventListener('alpine:init', () => {
            Alpine.data('scheduledProjectNameEditor', function () {
                return {
                    scheduledId: 0,
                    name: '',
                    original: '',
                    editing: false,
                    error: null,

                    init() {
                        this.scheduledId = parseInt(this.$el.dataset.scheduledId);
                        this.name = this.$el.dataset.projectName || '';
                        this.original = this.name;
                    },

                    startEdit() {
                        this.original = this.name;
                        this.error = null;
                        this.editing = true;
                        this.$nextTick(() => {
                            if (this.$refs.nameInput) {
                                this.$refs.nameInput.focus();
                                this.$refs.nameInput.select();
                            }
                        });
                    },

                    cancel() {
                        this.name = this.original;
                        this.editing = false;
                        this.error = null;
                    },

                    async save() {
                        if (!this.editing) {
                            return;
                        }
                        if (this.name.trim() === this.original.trim()) {
                            this.editing = false;
                            return;
                        }
                        const formData = new FormData();
                        formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);
                        formData.append('_method', 'PATCH');
                        formData.append('project_name', this.name.trim());
                        try {
                            const resp = await fetch(`/scheduled-projects/${this.scheduledId}`, {
                                method: 'POST',
                                headers: { 'Accept': 'application/json' },
                                body: formData,
                            });
                            const data = await resp.json();
                            if (resp.ok && data.success) {
                                this.original = data.project_name;
                                this.name = data.project_name;
                                this.error = null;
                                this.editing = false;
                            } else {
                                this.name = this.original;
                                this.editing = false;
                                this.error = data.message || 'Failed to save';
                            }
                        } catch (e) {
                            this.name = this.original;
                            this.editing = false;
                            this.error = 'An error occurred while saving';
                        }
                    },
                };
            });
        });
    </script>
    @endpush
</x-app-layout>
