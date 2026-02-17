<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-100 leading-tight">
            {{ __('Edit Tag') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-[#002200] border border-gray-700 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form method="POST" action="{{ route('tags.update', $tag) }}">
                        @csrf
                        @method('PATCH')

                        <div class="mb-4">
                            <label for="tag_name" class="block text-sm font-medium text-gray-300 mb-2">Tag Name</label>
                            <input type="text" name="tag_name" id="tag_name" required
                                   class="w-full rounded-md bg-gray-700 border-gray-600 text-gray-100 placeholder-gray-500 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                   value="{{ old('tag_name', $tag->tag_name) }}">
                            @error('tag_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div class="mb-6">
                            <label for="color" class="block text-sm font-medium text-gray-300 mb-2">Color</label>
                            <input type="color" name="color" id="color" required
                                   class="h-10 w-24 rounded-md border-gray-600 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                   value="{{ old('color', $tag->color) }}">
                            @error('color')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div class="flex items-center gap-4">
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700">
                                Update Tag
                            </button>
                            <a href="{{ route('tags.show', $tag) }}" class="text-sm text-gray-400 hover:text-gray-300">
                                Cancel
                            </a>
                        </div>
                    </form>

                    <form method="POST" action="{{ route('tags.destroy', $tag) }}" class="ml-auto">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-sm text-red-600 hover:underline" onclick="return confirm('Are you sure you want to delete this tag?')">
                            Delete Tag
                        </button>
                    </form>

                </div>
            </div>
        </div>
    </div>
</x-app-layout>
