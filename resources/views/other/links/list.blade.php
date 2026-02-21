<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-100 leading-tight">
                {{ __('Other Links') }}
            </h2>
            <a href="{{ route('tasks.create') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700">
                Add Task
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if ($groups->isEmpty())
                <p class="text-gray-500">I has no files :(</p>
            @else
                @foreach ($groups as $groupName => $files)
                    <div class="mb-8">
                        <h3 class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-3 pb-2 border-b border-gray-700">
                            {{ $groupName }}
                        </h3>
                        <ul class="space-y-2">
                            @foreach ($files as $file)
                                <li>
                                    <a href="{{ url('other-links/' . $file['routePath']) }}"
                                       class="text-gray-300 hover:text-gray-100 transition-colors duration-150">
                                        {{ $file['name'] }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            @endif
        </div>
    </div>
</x-app-layout>
