<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-100 leading-tight">
            Templates
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">

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

            <!-- My Templates -->
            <div>
                <h3 class="text-lg font-semibold text-gray-100 mb-4">My Templates</h3>

                @if($myTemplates->isEmpty())
                    <div class="bg-[#202020] border border-gray-700 rounded-lg p-6 text-gray-500">
                        You haven't saved any templates yet. Open a project and click <strong class="text-gray-400">Save as Template</strong> to create one.
                    </div>
                @else
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach($myTemplates as $template)
                            <div class="bg-[#202020] border border-gray-700 rounded-lg p-5 flex flex-col gap-3"
                                 x-data="{ showUse: false, showDelete: false }">

                                <div class="flex items-start justify-between gap-2">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="text-gray-100 font-medium">{{ $template->name }}</span>
                                            @if($template->is_public)
                                                <span class="text-xs px-1.5 py-0.5 rounded bg-blue-900/50 text-blue-300 border border-blue-700/50">Public</span>
                                            @else
                                                <span class="text-xs px-1.5 py-0.5 rounded bg-gray-700 text-gray-400 border border-gray-600">Private</span>
                                            @endif
                                        </div>
                                        @if($template->description)
                                            <p class="text-gray-400 text-sm mt-1">{{ $template->description }}</p>
                                        @endif
                                    </div>
                                </div>

                                <div class="text-xs text-gray-500 space-y-0.5">
                                    <div>Created {{ $template->created_at->format('M j, Y') }}</div>
                                    @php $lastUsed = $template->last_used_at; @endphp
                                    @if($lastUsed)
                                        <div>Last used {{ \Carbon\Carbon::parse($lastUsed)->format('M j, Y') }}</div>
                                    @else
                                        <div>Never used</div>
                                    @endif
                                </div>

                                <div class="flex gap-2 mt-auto">
                                    <button @click="showUse = true"
                                            class="flex-1 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded">
                                        Use Template
                                    </button>
                                    <button @click="showDelete = true"
                                            class="px-3 py-1.5 bg-gray-700 hover:bg-red-900/60 text-gray-300 hover:text-red-300 text-sm rounded border border-gray-600 hover:border-red-700/50">
                                        Delete
                                    </button>
                                </div>

                                <!-- Use Template Modal -->
                                <div x-show="showUse" x-cloak
                                     class="fixed inset-0 z-50 flex items-center justify-center bg-black/60"
                                     @keydown.escape.window="showUse = false">
                                    <div class="bg-gray-800 border border-gray-600 rounded-lg p-6 w-full max-w-md shadow-xl"
                                         @click.stop>
                                        <h4 class="text-gray-100 font-semibold mb-1">Create Project from Template</h4>
                                        <p class="text-gray-400 text-sm mb-4">Using: <span class="text-gray-200">{{ $template->name }}</span></p>
                                        <form method="POST" action="{{ route('templates.createFromTemplate', $template) }}">
                                            @csrf
                                            <label class="block text-sm text-gray-300 mb-1" for="pname_{{ $template->id }}">Project Name</label>
                                            <input id="pname_{{ $template->id }}"
                                                   type="text" name="project_name"
                                                   value="{{ $template->name }}"
                                                   required
                                                   class="w-full bg-gray-700 border border-gray-600 text-gray-100 rounded px-3 py-2 text-sm focus:outline-none focus:border-blue-500 mb-4">
                                            <div class="flex justify-end gap-2">
                                                <button type="button" @click="showUse = false"
                                                        class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-gray-300 rounded text-sm">
                                                    Cancel
                                                </button>
                                                <button type="submit"
                                                        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded text-sm">
                                                    Create Project
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>

                                <!-- Delete Confirm Modal -->
                                <div x-show="showDelete" x-cloak
                                     class="fixed inset-0 z-50 flex items-center justify-center bg-black/60"
                                     @keydown.escape.window="showDelete = false">
                                    <div class="bg-gray-800 border border-gray-600 rounded-lg p-6 w-full max-w-sm shadow-xl"
                                         @click.stop>
                                        <h4 class="text-gray-100 font-semibold mb-2">Delete Template?</h4>
                                        <p class="text-gray-400 text-sm mb-4">
                                            "<span class="text-gray-200">{{ $template->name }}</span>" will be permanently deleted.
                                            Projects created from it are not affected.
                                        </p>
                                        <form method="POST" action="{{ route('templates.destroy', $template) }}">
                                            @csrf
                                            @method('DELETE')
                                            <div class="flex justify-end gap-2">
                                                <button type="button" @click="showDelete = false"
                                                        class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-gray-300 rounded text-sm">
                                                    Cancel
                                                </button>
                                                <button type="submit"
                                                        class="px-4 py-2 bg-red-700 hover:bg-red-600 text-white rounded text-sm">
                                                    Delete
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>

                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- Public Templates from Others -->
            @if($publicTemplates->isNotEmpty())
                <div>
                    <h3 class="text-lg font-semibold text-gray-100 mb-4">Public Templates</h3>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach($publicTemplates as $template)
                            <div class="bg-[#202020] border border-gray-700 rounded-lg p-5 flex flex-col gap-3"
                                 x-data="{ showUse: false }">

                                <div>
                                    <span class="text-gray-100 font-medium">{{ $template->name }}</span>
                                    <span class="ml-2 text-xs text-gray-500">by {{ $template->creator->name }}</span>
                                    @if($template->description)
                                        <p class="text-gray-400 text-sm mt-1">{{ $template->description }}</p>
                                    @endif
                                </div>

                                <div class="text-xs text-gray-500 space-y-0.5">
                                    <div>Created {{ $template->created_at->format('M j, Y') }}</div>
                                    @php $lastUsed = $template->last_used_at; @endphp
                                    @if($lastUsed)
                                        <div>Last used {{ \Carbon\Carbon::parse($lastUsed)->format('M j, Y') }}</div>
                                    @else
                                        <div>Never used</div>
                                    @endif
                                </div>

                                <div class="mt-auto">
                                    <button @click="showUse = true"
                                            class="w-full px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded">
                                        Use Template
                                    </button>
                                </div>

                                <!-- Use Template Modal -->
                                <div x-show="showUse" x-cloak
                                     class="fixed inset-0 z-50 flex items-center justify-center bg-black/60"
                                     @keydown.escape.window="showUse = false">
                                    <div class="bg-gray-800 border border-gray-600 rounded-lg p-6 w-full max-w-md shadow-xl"
                                         @click.stop>
                                        <h4 class="text-gray-100 font-semibold mb-1">Create Project from Template</h4>
                                        <p class="text-gray-400 text-sm mb-4">Using: <span class="text-gray-200">{{ $template->name }}</span></p>
                                        <form method="POST" action="{{ route('templates.createFromTemplate', $template) }}">
                                            @csrf
                                            <label class="block text-sm text-gray-300 mb-1" for="pname_pub_{{ $template->id }}">Project Name</label>
                                            <input id="pname_pub_{{ $template->id }}"
                                                   type="text" name="project_name"
                                                   value="{{ $template->name }}"
                                                   required
                                                   class="w-full bg-gray-700 border border-gray-600 text-gray-100 rounded px-3 py-2 text-sm focus:outline-none focus:border-blue-500 mb-4">
                                            <div class="flex justify-end gap-2">
                                                <button type="button" @click="showUse = false"
                                                        class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-gray-300 rounded text-sm">
                                                    Cancel
                                                </button>
                                                <button type="submit"
                                                        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded text-sm">
                                                    Create Project
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>

                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
