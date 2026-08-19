<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Maps URL Template
    |--------------------------------------------------------------------------
    | URL template used to generate map links for tasks with show_map=true.
    | %s is replaced with the URL-encoded location string.
    | Change MAPS_URL_TEMPLATE in .env to use a different mapping service.
    */
    'maps_url_template' => env('MAPS_URL_TEMPLATE', 'https://maps.google.com/?q=%s'),

    /*
    |--------------------------------------------------------------------------
    | Location Truncate Length
    |--------------------------------------------------------------------------
    | Maximum characters shown for location in task list rows before truncating
    | with "…". Set LOCATION_TRUNCATE_LENGTH=0 in .env to disable truncation.
    */
    'location_truncate_length' => (int) env('LOCATION_TRUNCATE_LENGTH', 30),

    /*
    |--------------------------------------------------------------------------
    | Registration
    |--------------------------------------------------------------------------
    | Set DISABLE_REGISTRATION=true in .env to hide the registration routes.
    */
    'disable_registration' => env('DISABLE_REGISTRATION', false),

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    | Default number of items per page for paginated list views.
    */
    'pagination_per_page' => (int) env('PAGINATION_PER_PAGE', 100),

    /*
    |--------------------------------------------------------------------------
    | File Uploads
    |--------------------------------------------------------------------------
    | MAX_FILE_SIZE accepts a label like "22M" (validated in megabytes).
    | SCALE_LARGEST_TO is the max pixel dimension images are downscaled to.
    */
    'max_file_size' => env('MAX_FILE_SIZE', '22M'),
    'scale_largest_to' => (int) env('SCALE_LARGEST_TO', 2048),

    /*
    |--------------------------------------------------------------------------
    | Text Input Limits
    |--------------------------------------------------------------------------
    | Maximum characters for long text fields (descriptions, comments) and
    | for the bulk task quick-add input, plus the max number of lines/tasks
    | accepted in a single bulk submission.
    */
    'long_text_max_chars' => (int) env('LONG_TEXT_MAX_CHARS', 10000),
    'bulk_input_max_chars' => (int) env('BULK_INPUT_MAX_CHARS', 10000),
    'bulk_input_max_lines' => (int) env('BULK_INPUT_MAX_LINES', 100),

    /*
    |--------------------------------------------------------------------------
    | Todoist Import
    |--------------------------------------------------------------------------
    | Default Todoist API key used by the todoist:import command when
    | --todoist-api-key is not passed explicitly.
    */
    'todoist_key' => env('TODOIST_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Day PDF Export
    |--------------------------------------------------------------------------
    | Number of columns in the printable day-view PDF (the "Export PDF" button
    | on the day page). Clamped to 1-4 — below 1 is nonsensical, and the page
    | margins/gutter this layout uses were tuned by eye for up to 4 columns on
    | a portrait US Letter page; more than that gets too cramped to read.
    */
    'day_export_columns' => max(1, min(4, (int) env('DAY_EXPORT_COLUMNS', 2))),

    /*
    |--------------------------------------------------------------------------
    | Day PNG Export
    |--------------------------------------------------------------------------
    | A single tall image of the day's task list (no columns, no page breaks) —
    | for printing on a receipt/thermal printer, where a multi-column PDF just
    | means cutting and taping strips together. DAY_EXPORT_PNG_WIDTH is the
    | image width in pixels; text scales to it, so narrowing it in .env
    | reflows rather than shrinking into unreadability. 600px lands at the
    | wide end of what a typical 58-80mm thermal printer prints (~384-600px
    | at their usual 203dpi) — tune it to your printer via .env, no code
    | change needed. Clamped to 200-4000: below 200 can't fit a time gutter
    | next to wrapped text, above 4000 is well past any receipt printer and
    | risks an enormous image for no benefit.
    |
    | PNG_EXPORT_FONT_REGULAR / PNG_EXPORT_FONT_BOLD override the TrueType
    | font files used to draw text (DayPngExporter falls back through a short
    | list of common system font paths, then to GD's built-in bitmap font as
    | a last resort, if neither is set — see DayPngExporter::resolveFont()).
    */
    'day_export_png_width' => max(200, min(4000, (int) env('DAY_EXPORT_PNG_WIDTH', 600))),
    'day_export_png_font_regular' => env('PNG_EXPORT_FONT_REGULAR'),
    'day_export_png_font_bold' => env('PNG_EXPORT_FONT_BOLD'),
];
