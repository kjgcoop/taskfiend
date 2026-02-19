<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-100 leading-tight">
            {{ __('Edit Project') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-[#202020] border border-gray-700 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form method="POST" action="{{ route('projects.update', $project) }}" enctype="multipart/form-data">
                        @csrf
                        @method('PATCH')

                        <div class="mb-4">
                            <label for="name" class="block text-sm font-medium text-gray-300 mb-2">Project Name</label>
                            <input type="text" name="name" id="name" required
                                   class="w-full rounded-md bg-gray-700 border-gray-600 text-gray-100 placeholder-gray-500 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                   value="{{ old('name', $project->name) }}">
                            @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div class="mb-4">
                            <label for="description" class="block text-sm font-medium text-gray-300 mb-2">Description</label>
                            <textarea name="description" id="description" rows="4"
                                      class="w-full rounded-md bg-gray-700 border-gray-600 text-gray-100 placeholder-gray-500 shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('description', $project->description) }}</textarea>
                            @error('description')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div class="mb-4" x-data="{ removing: false }">
                            <label class="block text-sm font-medium text-gray-300 mb-2">Background Image</label>
                            @if($project->background_image)
                                <div class="mb-3">
                                    <div class="relative w-full h-32 rounded-md overflow-hidden border border-gray-600" x-show="!removing">
                                        <img src="{{ route('projects.background', $project) }}"
                                             alt="Current background"
                                             class="w-full h-full object-cover">
                                    </div>
                                    <div x-show="!removing" class="mt-2">
                                        <button type="button" @click="removing = true"
                                                class="text-sm text-red-400 hover:text-red-300 underline">
                                            Remove background
                                        </button>
                                    </div>
                                    <div x-show="removing" class="text-sm text-red-400 mt-1 flex items-center gap-2">
                                        <span>Background will be removed on save.</span>
                                        <button type="button" @click="removing = false"
                                                class="underline text-gray-400 hover:text-gray-200">Undo</button>
                                    </div>
                                    <input type="hidden" name="remove_background" :value="removing ? '1' : ''">
                                </div>
                            @endif
                            <input type="file" name="background_image"
                                   accept="image/jpeg,image/png,image/webp,image/gif,image/avif,image/heic,image/heif,.jpg,.jpeg,.png,.webp,.gif,.avif,.heic,.heif"
                                   class="block w-full text-sm text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-gray-700 file:text-gray-100 hover:file:bg-gray-600 bg-[#101010] border border-gray-600 rounded-md">
                            <p class="mt-1 text-xs text-gray-500">JPG, PNG, WebP, GIF, AVIF, HEIC &mdash; max 20 MB</p>
                            @error('background_image')<p class="mt-1 text-sm text-red-500">{{ $message }}</p>@enderror
                        </div>

                        <div class="mb-4">
                            <label for="status" class="block text-sm font-medium text-gray-300 mb-2">Status</label>
                            <select name="status" id="status"
                                    class="w-full rounded-md bg-gray-700 border-gray-600 text-gray-100 placeholder-gray-500 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="incomplete" {{ old('status', $project->status) === 'incomplete' ? 'selected' : '' }}>Incomplete</option>
                                <option value="done" {{ old('status', $project->status) === 'done' ? 'selected' : '' }}>Done</option>
                                <option value="archived" {{ old('status', $project->status) === 'archived' ? 'selected' : '' }}>Archived</option>
                            </select>
                            @error('status')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-300 mb-2">Assign To</label>
                            <div class="space-y-2">
                                @foreach($users as $user)
                                    <label class="flex items-center">
                                        <input type="checkbox" name="assignee_ids[]" value="{{ $user->id }}"
                                               class="rounded border-gray-600 bg-gray-700 text-blue-600 focus:ring-blue-500"
                                               {{ in_array($user->id, old('assignee_ids', $project->assignees->pluck('id')->toArray())) ? 'checked' : '' }}>
                                        <span class="ml-2 text-sm text-gray-300">{{ $user->name }} ({{ $user->email }})</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div class="flex items-center gap-4">
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700">
                                Update Project
                            </button>
                            <a href="{{ route('projects.show', $project) }}" class="text-sm text-gray-400 hover:text-gray-300">
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
