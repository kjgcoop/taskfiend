<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2 w-full" x-data="{ showSaveTemplate: false, showMenu: false }">
            {{-- Star / default toggle --}}
            @if($project->is_default)
                <span title="Default project" class="text-yellow-400 shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                    </svg>
                </span>
            @elseif($project->user_id === Auth::id() && $project->status === 'incomplete')
                <button id="set-default-btn"
                        onclick="setDefaultProject({{ $project->id }})"
                        title="Set as default project"
                        class="text-gray-600 hover:text-yellow-400 transition-colors shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 20 20" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                    </svg>
                </button>
            @endif

            {{-- Inline-editable project name + count badge --}}
            @php $isInactive = in_array($project->status, ['done', 'archived']); @endphp
            <div x-data="projectHeaderEditor({{ $project->id }}, @js($project->name))" class="flex items-center gap-2 min-w-0">
                <h2 x-show="!editing"
                    @click="{{ !$isInactive && $project->user_id === Auth::id() ? 'startEdit()' : '' }}"
                    class="font-semibold text-xl text-gray-100 leading-tight truncate {{ !$isInactive && $project->user_id === Auth::id() ? 'cursor-pointer hover:text-gray-300' : '' }}">
                    <span x-text="name"></span>
                </h2>
                <input x-show="editing" x-cloak x-ref="nameInput"
                       x-model="name"
                       @blur="save()"
                       @keydown.enter.prevent="save()"
                       @keydown.escape.prevent="cancel()"
                       class="font-semibold text-xl text-gray-100 bg-transparent border-b border-gray-400 focus:outline-none focus:border-blue-400 min-w-0 w-64" />
                <x-task-count-badge :count="$tasks->count()" :breakdown="$breakdown" />
            </div>

            {{-- Spacer --}}
            <div class="flex-1"></div>

            {{-- ID + copy link --}}
            <span class="text-sm text-gray-500 shrink-0">#{{ $project->id }}</span>
            <span x-data="{ copied: false }" class="shrink-0">
                <button @click="navigator.clipboard.writeText('{{ route('projects.show', $project) }}').then(() => { copied = true; setTimeout(() => copied = false, 2000) })"
                        title="Copy link"
                        class="text-gray-500 hover:text-gray-300 transition-colors">
                    <span x-show="!copied">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                        </svg>
                    </span>
                    <span x-show="copied" x-cloak class="text-xs text-green-400">Copied!</span>
                </button>
            </span>

            {{-- Three-dot menu --}}
            <div class="relative shrink-0" @click.outside="showMenu = false">
                <button @click="showMenu = !showMenu"
                        class="p-2 text-gray-400 hover:text-gray-100 hover:bg-gray-700 rounded transition-colors"
                        title="More options">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M10 6a2 2 0 110-4 2 2 0 010 4zm0 6a2 2 0 110-4 2 2 0 010 4zm0 6a2 2 0 110-4 2 2 0 010 4z" />
                    </svg>
                </button>
                <div x-show="showMenu" x-cloak
                     class="absolute right-0 mt-1 w-48 bg-gray-800 border border-gray-600 rounded shadow-lg z-10">
                    @if($project->user_id === Auth::id())
                        <button @click="showMenu = false; $dispatch('open-project-details')"
                                class="w-full text-left px-4 py-2 text-gray-200 hover:bg-gray-700">
                            Details
                        </button>
                    @endif
                    <a href="{{ route('projects.export-markdown', $project) }}"
                       class="block px-4 py-2 text-gray-200 hover:bg-gray-700">
                        Export .md
                    </a>
                    <a href="{{ route('projects.export-template', $project) }}"
                       class="block px-4 py-2 text-gray-200 hover:bg-gray-700">
                        Download Template
                    </a>
                    @if($project->user_id === Auth::id())
                        <button @click="showMenu = false; showSaveTemplate = true"
                                class="w-full text-left px-4 py-2 text-gray-200 hover:bg-gray-700">
                            Save as Template
                        </button>
                    @endif
                </div>
            </div>

            {{-- Save as Template Modal --}}
            <div x-show="showSaveTemplate" x-cloak
                 class="fixed inset-0 z-50 flex items-center justify-center bg-black/60"
                 @keydown.escape.window="showSaveTemplate = false">
                <div class="bg-gray-800 border border-gray-600 rounded-lg p-6 w-full max-w-md shadow-xl"
                     @click.stop>
                    <h4 class="text-gray-100 font-semibold mb-4">Save as Template</h4>
                    <form method="POST" action="{{ route('templates.store', $project) }}">
                        @csrf
                        <div class="mb-4">
                            <label class="block text-sm text-gray-300 mb-1" for="template_name">Template Name</label>
                            <input id="template_name" type="text" name="template_name"
                                   value="{{ $project->name }}"
                                   required
                                   class="w-full bg-gray-700 border border-gray-600 text-gray-100 rounded px-3 py-2 text-sm focus:outline-none focus:border-blue-500">
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm text-gray-300 mb-1" for="template_description">Description <span class="text-gray-500">(optional)</span></label>
                            <textarea id="template_description" name="template_description" rows="2"
                                      class="w-full bg-gray-700 border border-gray-600 text-gray-100 rounded px-3 py-2 text-sm focus:outline-none focus:border-blue-500 placeholder-gray-500"
                                      placeholder="What is this template for?"></textarea>
                        </div>
                        <div class="mb-5 flex items-center gap-2">
                            <input id="is_public" type="checkbox" name="is_public" value="1"
                                   class="rounded border-gray-600 bg-gray-700 text-blue-600 focus:ring-blue-500">
                            <label for="is_public" class="text-sm text-gray-300">Make public (visible to all users)</label>
                        </div>
                        <p class="text-xs text-gray-500 mb-4">Only incomplete tasks are included in the template.</p>
                        <div class="flex justify-end gap-2">
                            <button type="button" @click="showSaveTemplate = false"
                                    class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-gray-300 rounded text-sm">
                                Cancel
                            </button>
                            <button type="submit"
                                    class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded text-sm">
                                Save Template
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </x-slot>

    @php $isInactive = in_array($project->status, ['done', 'archived']); @endphp
    <div class="py-12 relative" @if($project->user_id === Auth::id()) x-data="projectEditor({{ $project->id }})" @endif
         @if($project->user_id === Auth::id()) @open-project-details.window="showDetails = true" @endif>
        @if($project->background_image)
            <div class="absolute inset-0" style="background-image: url('{{ route('projects.background', $project) }}'); background-attachment: fixed; background-position: center; background-size: cover; background-repeat: no-repeat;"></div>
            <div class="absolute inset-0 bg-black/65"></div>
        @endif
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6 relative">
            @if($project->status === 'archived')
                <div class="bg-amber-950/40 border border-amber-700/60 rounded-lg p-4 flex items-center gap-3">
                    <span class="text-amber-500 text-xl">🗄</span>
                    <div>
                        <p class="text-amber-400 font-semibold">This project is archived</p>
                        <p class="text-amber-600 text-sm mt-0.5">Tasks in this project are hidden from your task lists.</p>
                    </div>
                </div>
            @elseif($project->status === 'done')
                <div class="bg-green-950/40 border border-green-700/60 rounded-lg p-4 flex items-center gap-3">
                    <span class="text-green-500 text-xl">✓</span>
                    <div>
                        <p class="text-green-400 font-semibold">This project is done</p>
                        <p class="text-green-600 text-sm mt-0.5">Tasks in this project are hidden from your task lists.</p>
                    </div>
                </div>
            @endif

            {{-- Details Modal (triggered by "Details" in the three-dot menu) --}}
            @if($project->user_id === Auth::id())
            <div x-show="showDetails" x-cloak
                 class="fixed inset-0 z-50 flex items-center justify-center bg-black/60"
                 @keydown.escape.window="showDetails = false">
                <div class="bg-gray-800 border border-gray-600 rounded-lg p-6 w-full max-w-lg shadow-xl overflow-y-auto max-h-[90vh]"
                     @click.stop>
                    <div class="flex justify-between items-center mb-5">
                        <h4 class="text-gray-100 font-semibold text-lg">Project Details</h4>
                        <button @click="showDetails = false" class="text-gray-400 hover:text-gray-100">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    {{-- Name --}}
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-400 mb-1">Project Name</label>
                        @if(!$isInactive)
                            <div class="flex gap-2">
                                <input type="text" x-model="fields.name"
                                       @keydown.enter="saveField('name')"
                                       class="flex-1 rounded-md bg-gray-700 border-gray-600 text-gray-100 placeholder-gray-500 shadow-sm focus:border-blue-500 focus:ring-blue-500 px-3 py-2 text-sm">
                                <button @click="saveField('name')"
                                        class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                                    Save
                                </button>
                            </div>
                        @else
                            <p class="text-gray-300 px-1">{{ $project->name }}</p>
                        @endif
                    </div>

                    {{-- Description --}}
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-400 mb-1">Description</label>
                        @if(!$isInactive)
                            <textarea x-model="fields.description" rows="4"
                                      @keydown.ctrl.enter="saveField('description')"
                                      class="w-full rounded-md bg-gray-700 border-gray-600 text-gray-100 placeholder-gray-500 shadow-sm focus:border-blue-500 focus:ring-blue-500 px-3 py-2 text-sm"
                                      placeholder="Add a description..."></textarea>
                            <div class="flex items-center justify-between mt-1">
                                <p class="text-xs text-gray-500">Ctrl+Enter to save</p>
                                <button @click="saveField('description')"
                                        class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                                    Save
                                </button>
                            </div>
                        @else
                            <div class="markdown-body text-gray-300 px-1">
                                @if($project->description)
                                    {!! render_body($project->description) !!}
                                @else
                                    <p class="text-gray-500 italic">No description</p>
                                @endif
                            </div>
                        @endif
                    </div>

                    {{-- Status --}}
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-400 mb-1">Status</label>
                        @if(!$isInactive)
                            <div class="flex gap-2">
                                <select x-model="fields.status"
                                        class="flex-1 rounded-md bg-gray-700 border-gray-600 text-gray-100 shadow-sm focus:border-blue-500 focus:ring-blue-500 px-3 py-2 text-sm">
                                    <option value="incomplete">Incomplete</option>
                                    <option value="done">Done</option>
                                    <option value="archived">Archived</option>
                                </select>
                                <button @click="saveField('status')"
                                        class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                                    Save
                                </button>
                            </div>
                        @else
                            <div class="flex gap-2">
                                <select x-model="fields.status"
                                        class="flex-1 rounded-md bg-gray-700 border-gray-600 text-gray-100 shadow-sm focus:border-blue-500 focus:ring-blue-500 px-3 py-2 text-sm">
                                    <option value="incomplete">Incomplete</option>
                                    <option value="done">Done</option>
                                    <option value="archived">Archived</option>
                                </select>
                                <button @click="saveField('status')"
                                        class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                                    Save
                                </button>
                            </div>
                        @endif
                    </div>

                    {{-- Assignees --}}
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-400 mb-1">Assigned To</label>
                        @if(!$isInactive)
                            <div class="space-y-2 mb-2 max-h-40 overflow-y-auto border border-gray-600 bg-[#101010] rounded p-3">
                                @foreach($users as $user)
                                    <label class="flex items-center">
                                        <input type="checkbox" value="{{ $user->id }}" x-model="fields.assignee_ids"
                                               class="rounded border-gray-600 bg-gray-700 text-blue-600 focus:ring-blue-500">
                                        <span class="ml-2 text-sm text-gray-300">{{ $user->name }} ({{ $user->email }})</span>
                                    </label>
                                @endforeach
                            </div>
                            <button @click="saveField('assignee_ids')"
                                    class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                                Save Assignees
                            </button>
                        @else
                            @if($project->assignees->count() > 0)
                                @foreach($project->assignees as $a)
                                    <p class="text-sm text-gray-300">{{ $a->name }}</p>
                                @endforeach
                            @else
                                <p class="text-gray-500 italic text-sm">No assignees</p>
                            @endif
                        @endif
                    </div>

                    {{-- Background Image --}}
                    @if(!$isInactive)
                    <div class="mb-4" x-data="{ showUpload: false }">
                        <label class="block text-sm font-medium text-gray-400 mb-1">Background Image</label>
                        @if($project->background_image)
                            <img src="{{ route('projects.background', $project) }}"
                                 alt="Project background"
                                 class="rounded-md border border-gray-600 mb-2"
                                 style="height: 80px; width: auto;">
                            <div class="flex items-center gap-3">
                                <button @click="showUpload = !showUpload"
                                        class="text-sm text-gray-400 hover:text-gray-200 underline">
                                    Replace
                                </button>
                                <form method="POST" action="{{ route('projects.background.remove', $project) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-sm text-red-400 hover:text-red-300 underline">
                                        Remove
                                    </button>
                                </form>
                            </div>
                        @else
                            <button @click="showUpload = !showUpload"
                                    class="text-sm text-gray-400 hover:text-gray-200 underline">
                                Add background image
                            </button>
                        @endif
                        <div x-show="showUpload" x-cloak class="mt-2">
                            <form method="POST" action="{{ route('projects.background.upload', $project) }}"
                                  enctype="multipart/form-data" class="flex items-center gap-2 flex-wrap">
                                @csrf
                                <input type="file" name="background_image" required
                                       accept="image/jpeg,image/png,image/webp,image/gif,image/avif,image/heic,image/heif,.jpg,.jpeg,.png,.webp,.gif,.avif,.heic,.heif"
                                       class="block text-sm text-gray-400 file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-gray-700 file:text-gray-100 hover:file:bg-gray-600 bg-[#101010] border border-gray-600 rounded-md">
                                <button type="submit"
                                        class="px-3 py-1.5 bg-blue-600 text-white text-xs rounded hover:bg-blue-700 whitespace-nowrap">
                                    Upload
                                </button>
                            </form>
                            <p class="mt-1 text-xs text-gray-500">JPG, PNG, WebP, GIF, AVIF, HEIC &mdash; max 20 MB</p>
                        </div>
                    </div>
                    @endif

                    {{-- Created By (read-only) --}}
                    <div class="mb-5">
                        <label class="block text-sm font-medium text-gray-400 mb-1">Created By</label>
                        <p class="text-sm text-gray-300">{{ $project->creator->name }}</p>
                    </div>

                    <div class="flex justify-end">
                        <button @click="showDetails = false"
                                class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-gray-300 rounded text-sm">
                            Close
                        </button>
                    </div>
                </div>
            </div>
            @endif

            @if(false) {{-- Old project details card — removed, content moved to Details modal --}}
            <div>
                @php $isInactive = false; @endphp

                @if($project->user_id === Auth::id())
                    <!-- Project name -->
                    <div class="mb-4">
                        <span class="text-sm font-medium text-gray-500">Project Name</span>
                        @if(!$isInactive)
                            <div @click="startEdit('name')" x-show="!editing.name" class="mt-1 cursor-pointer hover:bg-gray-700 p-2 rounded">
                                <p class="text-lg font-semibold text-gray-100">{{ $project->name }}</p>
                            </div>
                            <div x-show="editing.name" class="mt-1">
                                <input type="text" x-model="fields.name"
                                       @keydown.enter="saveField('name')"
                                       @keydown.escape="cancelEdit('name')"
                                       class="w-full rounded-md bg-gray-700 border-gray-600 text-gray-100 placeholder-gray-500 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <div class="flex gap-2 mt-2">
                                    <button @click="saveField('name')"
                                            class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                                        Save
                                    </button>
                                    <button @click="cancelEdit('name')"
                                            class="px-3 py-1 bg-gray-700 text-gray-300 text-sm rounded hover:bg-gray-600">
                                        Cancel
                                    </button>
                                </div>
                            </div>
                        @else
                            <div class="mt-1 p-2">
                                <p class="text-lg font-semibold text-gray-100">{{ $project->name }}</p>
                            </div>
                        @endif
                    </div>

                    <!-- Description -->
                    <div class="mt-4">
                        <span class="text-sm font-medium text-gray-500">Description</span>
                        @if(!$isInactive)
                            <div @click="startEdit('description')" x-show="!editing.description" class="markdown-body mt-1 cursor-pointer hover:bg-gray-700 p-2 rounded min-h-[40px]">
                                @if($project->description)
                                    {!! render_body($project->description) !!}
                                @else
                                    <p class="text-gray-400 italic">Click to add description</p>
                                @endif
                            </div>
                            <div x-show="editing.description" class="mt-1">
                                <textarea x-model="fields.description" rows="3"
                                          @keydown.ctrl.enter="saveField('description')"
                                          @keydown.escape="cancelEdit('description')"
                                          class="w-full rounded-md bg-gray-700 border-gray-600 text-gray-100 placeholder-gray-500 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                          placeholder="Add a description..."></textarea>
                                <p class="mt-1 text-xs text-gray-500">Ctrl+Enter to save, Escape to cancel</p>
                                <div class="flex gap-2 mt-2">
                                    <button @click="saveField('description')"
                                            class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                                        Save
                                    </button>
                                    <button @click="cancelEdit('description')"
                                            class="px-3 py-1 bg-gray-700 text-gray-300 text-sm rounded hover:bg-gray-600">
                                        Cancel
                                    </button>
                                </div>
                            </div>
                        @else
                            <div class="markdown-body mt-1 p-2 min-h-[40px]">
                                @if($project->description)
                                    {!! render_body($project->description) !!}
                                @else
                                    <p class="text-gray-600 italic">No description</p>
                                @endif
                            </div>
                        @endif
                    </div>

                    <!-- Read more: Status, Assignees, Background Image -->
                    <details class="mt-4 group">
                        <summary class="cursor-pointer text-sm text-blue-400 hover:text-blue-300 list-none flex items-center gap-1">
                            <span class="group-open:hidden">Read more</span>
                            <span class="hidden group-open:inline">Show less</span>
                        </summary>
                        <div class="mt-4 space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <!-- Status (editable) -->
                                <div>
                                    <span class="text-sm font-medium text-gray-500">Status</span>
                                    <div @click="startEdit('status')" x-show="!editing.status" class="mt-1 cursor-pointer hover:bg-gray-700 p-2 rounded">
                                        <span class="inline-block px-2 py-1 text-xs rounded
                                            @if($project->status === 'done') bg-green-100 text-green-800
                                            @elseif($project->status === 'archived') bg-gray-100 text-gray-800
                                            @else bg-blue-100 text-blue-800 @endif">
                                            {{ ucfirst($project->status) }}
                                        </span>
                                    </div>
                                    <div x-show="editing.status" class="mt-1">
                                        <select x-model="fields.status"
                                                @keydown.escape="cancelEdit('status')"
                                                class="w-full rounded-md bg-gray-700 border-gray-600 text-gray-100 placeholder-gray-500 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                            <option value="incomplete">Incomplete</option>
                                            <option value="done">Done</option>
                                            <option value="archived">Archived</option>
                                        </select>
                                        <div class="flex gap-2 mt-2">
                                            <button @click="saveField('status')"
                                                    class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                                                Save
                                            </button>
                                            <button @click="cancelEdit('status')"
                                                    class="px-3 py-1 bg-gray-700 text-gray-300 text-sm rounded hover:bg-gray-600">
                                                Cancel
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Created By (read-only) -->
                                <div>
                                    <span class="text-sm font-medium text-gray-500">Created By</span>
                                    <p class="mt-1 text-gray-300">{{ $project->creator->name }}</p>
                                </div>
                            </div>

                            <!-- Assignees -->
                            <div>
                                <span class="text-sm font-medium text-gray-500">Assigned To</span>
                                @if(!$isInactive)
                                    <div @click="startEdit('assignee_ids')" x-show="!editing.assignee_ids" class="mt-1 cursor-pointer hover:bg-gray-700 p-2 rounded min-h-[40px]">
                                        @if($project->assignees->count() > 0)
                                            <div class="space-y-1">
                                                @foreach($project->assignees as $assignee)
                                                    <p class="text-sm text-gray-300">{{ $assignee->name }}</p>
                                                @endforeach
                                            </div>
                                        @else
                                            <p class="text-gray-400 italic">Click to add assignees</p>
                                        @endif
                                    </div>
                                    <div x-show="editing.assignee_ids" class="mt-1">
                                        <div class="space-y-2 mb-2 max-h-48 overflow-y-auto border border-gray-600 bg-[#101010] rounded p-3">
                                            @foreach($users as $user)
                                                <label class="flex items-center">
                                                    <input type="checkbox" value="{{ $user->id }}" x-model="fields.assignee_ids"
                                                           class="rounded border-gray-600 bg-gray-700 text-blue-600 focus:ring-blue-500">
                                                    <span class="ml-2 text-sm text-gray-300">{{ $user->name }} ({{ $user->email }})</span>
                                                </label>
                                            @endforeach
                                        </div>
                                        <div class="flex gap-2 mt-2">
                                            <button @click="saveField('assignee_ids')"
                                                    class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                                                Save
                                            </button>
                                            <button @click="cancelEdit('assignee_ids')"
                                                    class="px-3 py-1 bg-gray-700 text-gray-300 text-sm rounded hover:bg-gray-600">
                                                Cancel
                                            </button>
                                        </div>
                                    </div>
                                @else
                                    <div class="mt-1 p-2 min-h-[40px]">
                                        @if($project->assignees->count() > 0)
                                            <div class="space-y-1">
                                                @foreach($project->assignees as $assignee)
                                                    <p class="text-sm text-gray-300">{{ $assignee->name }}</p>
                                                @endforeach
                                            </div>
                                        @else
                                            <p class="text-gray-600 italic">No assignees</p>
                                        @endif
                                    </div>
                                @endif
                            </div>

                            <!-- Background Image (only editable when project is active) -->
                            @if(!$isInactive)
                            <div x-data="{ showUpload: false }">
                                <span class="text-sm font-medium text-gray-500">Background Image</span>
                                @if($project->background_image)
                                    <div class="mt-1">
                                        <div class="mb-2">
                                            <img src="{{ route('projects.background', $project) }}"
                                                 alt="Project background"
                                                 class="rounded-md border border-gray-600"
                                                 style="height: 100px; width: auto;">
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <button @click="showUpload = !showUpload"
                                                    class="text-sm text-gray-400 hover:text-gray-200 underline">
                                                Replace
                                            </button>
                                            <form method="POST" action="{{ route('projects.background.remove', $project) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-sm text-red-400 hover:text-red-300 underline">
                                                    Remove
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                @else
                                    <div class="mt-1">
                                        <button @click="showUpload = !showUpload"
                                                class="text-sm text-gray-400 hover:text-gray-200 underline">
                                            Add background image
                                        </button>
                                    </div>
                                @endif
                                <div x-show="showUpload" x-cloak class="mt-2">
                                    <form method="POST" action="{{ route('projects.background.upload', $project) }}"
                                          enctype="multipart/form-data" class="flex items-center gap-2 flex-wrap">
                                        @csrf
                                        <input type="file" name="background_image" required
                                               accept="image/jpeg,image/png,image/webp,image/gif,image/avif,image/heic,image/heif,.jpg,.jpeg,.png,.webp,.gif,.avif,.heic,.heif"
                                               class="block text-sm text-gray-400 file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-gray-700 file:text-gray-100 hover:file:bg-gray-600 bg-[#101010] border border-gray-600 rounded-md">
                                        <button type="submit"
                                                class="px-3 py-1.5 bg-blue-600 text-white text-xs rounded hover:bg-blue-700 whitespace-nowrap">
                                            Upload
                                        </button>
                                        <button type="button" @click="showUpload = false"
                                                class="px-3 py-1.5 bg-gray-700 text-gray-300 text-xs rounded hover:bg-gray-600">
                                            Cancel
                                        </button>
                                    </form>
                                    <p class="mt-1 text-xs text-gray-500">JPG, PNG, WebP, GIF, AVIF, HEIC &mdash; max 20 MB</p>
                                    @error('background_image')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
                                </div>
                            </div>
                            @endif {{-- !$isInactive --}}
                        </div>
                    </details>

                @else
                    {{-- Read-only view for non-creators --}}
                    @if($project->description)
                        <div class="mb-4">
                            <span class="text-sm font-medium text-gray-500">Description</span>
                            <div class="markdown-body mt-1 text-gray-300">{!! render_body($project->description) !!}</div>
                        </div>
                    @endif

                    <!-- Read more: Status, Created By, Assignees -->
                    <details class="mt-2 group">
                        <summary class="cursor-pointer text-sm text-blue-400 hover:text-blue-300 list-none flex items-center gap-1">
                            <span class="group-open:hidden">Read more</span>
                            <span class="hidden group-open:inline">Show less</span>
                        </summary>
                        <div class="mt-4 space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <span class="text-sm font-medium text-gray-500">Status</span>
                                    <p class="mt-1">
                                        <span class="inline-block px-2 py-1 text-xs rounded
                                            @if($project->status === 'done') bg-green-100 text-green-800
                                            @elseif($project->status === 'archived') bg-gray-100 text-gray-800
                                            @else bg-blue-100 text-blue-800 @endif">
                                            {{ ucfirst($project->status) }}
                                        </span>
                                    </p>
                                </div>
                                <div>
                                    <span class="text-sm font-medium text-gray-500">Created By</span>
                                    <p class="mt-1 text-gray-300">{{ $project->creator->name }}</p>
                                </div>
                            </div>

                            @if($project->assignees->count() > 0)
                                <div>
                                    <span class="text-sm font-medium text-gray-500">Assigned To</span>
                                    <div class="mt-1 space-y-1">
                                        @foreach($project->assignees as $assignee)
                                            <p class="text-sm text-gray-300">{{ $assignee->name }}</p>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    </details>
                @endif
            </div>
            @endif {{-- end removed details card --}}

            <!-- Project Tasks -->
            <div class="bg-[#202020] border border-gray-700 shadow-sm sm:rounded-lg p-6" x-data="taskFilter(@js($projects), @js($tags), @js($users), @js($locations))">
                <div class="flex items-center justify-between mb-4">
                    <button type="button"
                            @click="showIncomplete = !showIncomplete"
                            title="Toggle task list"
                            class="text-gray-500 hover:text-gray-300 transition-colors flex-shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg"
                             class="h-4 w-4 transition-transform duration-150"
                             :class="showIncomplete ? 'rotate-90' : ''"
                             fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                    <div class="flex items-center gap-3">
                        <label for="sort-results" class="text-sm text-gray-400">Sort:</label>
                        <select id="sort-select" onchange="(function(v){const p=new URLSearchParams(window.location.search);p.set('sort',v);localStorage.setItem('task_sort_'+window.location.pathname,v);window.location.href=window.location.pathname+'?'+p.toString()})(this.value)"
                                class="text-sm bg-gray-700 border border-gray-600 rounded px-2 py-1 text-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="date" {{ $sort === 'date' ? 'selected' : '' }}>Date & Time</option>
                            <option value="created" {{ $sort === 'created' ? 'selected' : '' }}>Date Added</option>
                            <option value="name" {{ $sort === 'name' ? 'selected' : '' }}>Name</option>
                            <option value="duration" {{ $sort === 'duration' ? 'selected' : '' }}>Duration</option>
                            <option value="location" {{ $sort === 'location' ? 'selected' : '' }}>Location</option>
                            <option value="custom" {{ $sort === 'custom' ? 'selected' : '' }}>Custom Sort</option>
                        </select>
                        @if($sort !== 'custom')
                        <button onclick="toggleSortReversed()"
                                title="{{ request()->boolean('reversed') ? 'Reversed — click to restore' : 'Reverse sort order' }}"
                                class="p-1 rounded transition-colors {{ request()->boolean('reversed') ? 'text-blue-400 hover:text-blue-300' : 'text-gray-500 hover:text-gray-300' }}">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7 16V4M3 8l4-4 4 4M17 8v12M13 16l4 4 4-4" />
                            </svg>
                        </button>
                        @endif
                    </div>
                </div>
                <div x-show="showIncomplete">
                    @if(!$isInactive)
                        <x-task-input-bar :project-id="$project->id" filter-placeholder="Filter tasks... (@ tag)" />
                    @else
                        <div class="mb-4">
                            <input type="text"
                                   x-model="query"
                                   x-on:input="filterTasks()"
                                   x-on:keydown.escape="clearFilter()"
                                   placeholder="Filter tasks... (@ tag)"
                                   class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-md text-gray-100 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                    @endif
                    <div x-ref="taskContainer">
                        <x-task-list :tasks="$tasks" :read-only="$isInactive" :sortable="$sort === 'custom'" :reorder-url="route('projects.reorderTasks', $project)" />
                    </div>
                    <div x-show="noResults" x-cloak class="bg-[#202020] p-8 rounded-lg text-center text-gray-400 border border-gray-700">
                        No tasks match your filter.
                    </div>
                </div>

                <x-completed-tasks-section
                    :tasks="$completedTasks"
                    :read-only="$isInactive"
                    :total-count="$completedTasksTotal"
                    :has-more="$completedTasksHasMore"
                    :next-page="2"
                    :ajax-url="$completedTasksHasMore ? route('projects.completedTasks', $project) : null"
                />
                <x-completed-tasks-section
                    :tasks="$archivedTasks"
                    label="Archived tasks"
                    :read-only="true"
                    :show-as-archived="true"
                    :total-count="$archivedTasksTotal"
                    :has-more="$archivedTasksHasMore"
                    :next-page="2"
                    :ajax-url="$archivedTasksHasMore ? route('projects.archivedTasks', $project) : null"
                />
            </div>
        </div>
    </div>

    @if($project->user_id === Auth::id())
    @push('scripts')
    <script>
        function projectHeaderEditor(projectId, initialName) {
            return {
                projectId: projectId,
                name: initialName,
                original: initialName,
                editing: false,

                startEdit() {
                    this.original = this.name;
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
                },

                async save() {
                    if (this.name.trim() === this.original.trim()) {
                        this.editing = false;
                        return;
                    }
                    const formData = new FormData();
                    formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);
                    formData.append('field', 'name');
                    formData.append('value', this.name.trim());
                    try {
                        const resp = await fetch(`/projects/${this.projectId}/update-field`, {
                            method: 'POST',
                            body: formData,
                        });
                        const data = await resp.json();
                        if (data.success) {
                            this.original = this.name.trim();
                            this.editing = false;
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
        }

        function projectEditor(projectId) {
            return {
                projectId: projectId,
                showDetails: false,
                editing: {},
                fields: {
                    name: @js($project->name),
                    description: @js($project->description ?? ''),
                    status: @js($project->status),
                    assignee_ids: @js($project->assignees->pluck('id')->toArray()),
                },
                original: {},

                init() {
                    this.original = JSON.parse(JSON.stringify(this.fields));
                },

                startEdit(field) {
                    this.editing[field] = true;
                    if (field === 'name') {
                        this.$nextTick(() => {
                            const input = this.$el.querySelector('input[x-model="fields.name"]');
                            if (input) { input.focus(); input.select(); }
                        });
                    }
                    if (field === 'description') {
                        this.$nextTick(() => {
                            const ta = this.$el.querySelector('textarea[x-model="fields.description"]');
                            if (ta) ta.focus();
                        });
                    }
                },

                cancelEdit(field) {
                    this.editing[field] = false;
                    this.fields[field] = JSON.parse(JSON.stringify(this.original[field]));
                },

                async saveField(field) {
                    try {
                        const formData = new FormData();
                        formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);
                        formData.append('field', field);

                        if (Array.isArray(this.fields[field])) {
                            this.fields[field].forEach(value => {
                                formData.append(field + '[]', value);
                            });
                        } else {
                            formData.append('value', this.fields[field]);
                        }

                        const response = await fetch(`/projects/${this.projectId}/update-field`, {
                            method: 'POST',
                            body: formData,
                        });

                        const data = await response.json();

                        if (data.success) {
                            this.original[field] = JSON.parse(JSON.stringify(this.fields[field]));
                            this.editing[field] = false;
                            window.location.reload();
                        } else {
                            alert('Error: ' + (data.message || 'Failed to update'));
                            this.fields[field] = JSON.parse(JSON.stringify(this.original[field]));
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        alert('An error occurred while saving');
                        this.fields[field] = JSON.parse(JSON.stringify(this.original[field]));
                    }
                },
            };
        }
    </script>
    @endpush
    @endif

<script>
function setDefaultProject(projectId) {
    fetch(`/projects/${projectId}/set-default`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
        },
    }).then(r => r.json()).then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert(data.message || 'Could not set default project.');
        }
    });
}
</script>
</x-app-layout>
