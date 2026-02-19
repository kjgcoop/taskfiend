<section>
    <header>
        <h2 class="text-lg font-medium text-gray-100">
            {{ __('Export & Import Data') }}
        </h2>

        <p class="mt-1 text-sm text-gray-400">
            {{ __('Download a complete backup of all your data.') }}
        </p>
    </header>

    <div class="mt-6 space-y-4">
        <div>
            <a href="{{ route('export.all') }}" class="inline-flex items-center px-4 py-2 bg-gray-700 border border-gray-600 rounded-md font-semibold text-xs text-gray-100 uppercase tracking-widest hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150">
                {{ __('Export All My Data') }}
            </a>
            <p class="mt-2 text-xs text-gray-500">
                {{ __('Downloads a ZIP file containing all your projects, tasks, tags, comments, attachments, and change logs.') }}
            </p>
        </div>

        {{-- Import disabled: the importer matches records by ID with no ownership checks,
             which can silently overwrite other users' data on a shared server.
             Needs ID remapping before it can be safely re-enabled. --}}
        <div>
            <p class="text-sm text-gray-500 italic">
                {{ __('Data import is temporarily unavailable.') }}
            </p>
        </div>
    </div>
</section>
