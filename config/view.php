<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | View Storage Paths
    |--------------------------------------------------------------------------
    |
    | Laravel Boost renders its AI guidelines with Blade during development.
    | The application has no views of its own, so only a compiled path in
    | the ignored "tmp" directory is needed for the rendered templates.
    |
    */

    'paths' => [],

    'compiled' => base_path('tmp/views'),

];
