<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | View Storage Paths
    |--------------------------------------------------------------------------
    |
    | The update command renders its agent prompts with Blade. Views are
    | compiled into the system temp directory because the PHAR is read
    | only, so nothing can be written next to the application itself.
    |
    */

    'paths' => [
        base_path('resources/views'),
    ],

    'compiled' => env('VIEW_COMPILED_PATH', sys_get_temp_dir().'/laravel-starter-kit-cli/views'),

];
