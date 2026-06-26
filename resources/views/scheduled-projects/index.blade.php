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

            @if($scheduled->isEmpty())
                <div class="bg-[#202020] border border-gray-700 rounded-lg p-8 text-center text-gray-500">
                    No projects are scheduled. Use a template and pick a future date to schedule one.
                </div>
            @else
                <div class="bg-[#202020] border border-gray-700 rounded-lg divide-y divide-gray-700">
                    @foreach($scheduled as $item)
                        <div class="flex items-center justify-between gap-4 px-5 py-4">
                            <div class="flex-1 min-w-0">
                                <div class="text-gray-100 font-medium truncate">{{ $item->project_name }}</div>
                                <div class="text-sm text-gray-400 mt-0.5">
                                    From template: <span class="text-gray-300">{{ $item->template?->name ?? 'Deleted template' }}</span>
                                </div>
                            </div>
                            <div class="text-sm text-gray-300 whitespace-nowrap">
                                {{ $item->start_date->format('l, M j, Y') }}
                            </div>
                            <form method="POST" action="{{ route('scheduled-projects.destroy', $item) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        class="text-sm px-3 py-1.5 bg-gray-700 hover:bg-red-900/60 text-gray-300 hover:text-red-300 rounded border border-gray-600 hover:border-red-700/50">
                                    Cancel
                                </button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
