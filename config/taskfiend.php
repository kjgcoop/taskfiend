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
];
