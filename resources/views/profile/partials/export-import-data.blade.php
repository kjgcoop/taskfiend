<section>
    <header>
        <h2 class="text-lg font-medium text-gray-100">
            {{ __('Export & Import Data') }}
        </h2>

        <p class="mt-1 text-sm text-gray-400">
            {{ __('Download a complete backup of all your data or import data from a previous export.') }}
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

        <div>
            <form action="{{ route('import.all') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="flex items-center space-x-4">
                    <input type="file" name="import_file" accept=".zip" required class="block w-full text-sm text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-gray-700 file:text-gray-100 hover:file:bg-gray-600 bg-[#0a1a0a] border border-gray-600 rounded-md">
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-gray-700 border border-gray-600 rounded-md font-semibold text-xs text-gray-100 uppercase tracking-widest hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150">
                        {{ __('Import Data') }}
                    </button>
                </div>
                <p class="mt-2 text-xs text-gray-500">
                    {{ __('Upload a ZIP file from a previous export. Existing data will be updated with values from the import.') }}
                </p>
            </form>
        </div>
    </div>
</section>
