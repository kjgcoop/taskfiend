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
];
