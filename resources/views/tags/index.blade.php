<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-100 leading-tight">
                {{ __('Tags') }}
            </h2>
            <a href="{{ route('tags.create') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700">
                New Tag
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="grid gap-4 md:grid-cols-3 lg:grid-cols-4">
                @forelse($tags as $tag)
                    <div class="bg-[#202020] border border-gray-700 p-4 rounded-lg shadow hover:shadow-md transition cursor-pointer border-l-4"
                         style="border-left-color: {{ $tag->color }}"
                         onclick="window.location='{{ route('tags.show', $tag) }}'">
                        <h3 class="font-semibold text-lg" style="color: {{ $tag->color }}">
                            {{ $tag->tag_name }}
                        </h3>
                        <p class="text-sm text-gray-500 mt-2">{{ $tag->tasks_count }} tasks</p>
                    </div>
                @empty
                    <div class="col-span-full bg-[#202020] border border-gray-700 p-8 rounded-lg text-center text-gray-500">
                        No tags yet. Create your first tag!
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
