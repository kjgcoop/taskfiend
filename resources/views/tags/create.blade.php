<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-100 leading-tight">
            {{ __('Create Tag') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-[#202020] border border-gray-700 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form method="POST" action="{{ route('tags.store') }}">
                        @csrf

                        <div class="mb-4">
                            <label for="tag_name" class="block text-sm font-medium text-gray-300 mb-2">Tag Name</label>
                            <input type="text" name="tag_name" id="tag_name" required
                                   class="w-full rounded-md bg-gray-700 border-gray-600 text-gray-100 placeholder-gray-500 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                   value="{{ old('tag_name') }}">
                            @error('tag_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div class="mb-6" x-data="colorPicker" data-color="{{ old('color', '#3B82F6') }}">
                            <label class="block text-sm font-medium text-gray-300 mb-2">Color</label>
                            <input type="hidden" name="color" :value="selected">
                            <div class="flex flex-wrap gap-2">
                                @foreach(['#EF4444','#F97316','#F59E0B','#EAB308','#84CC16','#22C55E','#10B981','#14B8A6','#06B6D4','#3B82F6','#6366F1','#8B5CF6','#A855F7','#EC4899','#F43F5E','#78716C','#6B7280','#64748B','#0EA5E9','#0D9488','#15803D','#B45309','#C2410C','#9F1239'] as $c)
                                <button type="button"
                                    @click="selected = '{{ $c }}'"
                                    :class="selected === '{{ $c }}' ? 'ring-2 ring-offset-2 ring-offset-[#202020] ring-white scale-110' : 'hover:scale-110'"
                                    class="w-8 h-8 rounded-full transition-transform"
                                    style="background-color: {{ $c }};"
                                    title="{{ $c }}">
                                </button>
                                @endforeach
                            </div>
                            <div class="mt-3 flex items-center gap-2">
                                <div class="w-5 h-5 rounded-full border border-gray-600 flex-shrink-0" :style="'background-color:' + selected"></div>
                                <span class="text-gray-400 text-sm">#</span>
                                <input type="text" maxlength="6"
                                    :value="selected.replace('#', '')"
                                    @input="if ($event.target.value.match(/^[0-9a-fA-F]{6}$/)) selected = '#' + $event.target.value"
                                    class="w-24 rounded-md bg-gray-700 border-gray-600 text-gray-100 text-sm px-2 py-1 focus:border-blue-500 focus:ring-blue-500"
                                    placeholder="3B82F6">
                            </div>
                            @error('color')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div class="flex items-center gap-4">
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700">
                                Create Tag
                            </button>
                            <a href="{{ route('tags.index') }}" class="text-sm text-gray-400 hover:text-gray-300">
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
