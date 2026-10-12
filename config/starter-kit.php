<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Starter Kit Package
    |--------------------------------------------------------------------------
    |
    | The Composer package that new projects are created from. The latest
    | stable release of this package is resolved from Packagist, so any
    | override must be a published package with tagged releases.
    |
    */

    'package' => env('STARTER_KIT_PACKAGE', 'shire-studio/laravel-starter-kit'),

    /*
    |--------------------------------------------------------------------------
    | Release Manifest Filename
    |--------------------------------------------------------------------------
    |
    | The file written to the root of every new project to record the starter
    | kit release it was created from. The update command reads it back to
    | work out which upstream changes the project has not yet received.
    |
    */

    'manifest_filename' => env('STARTER_KIT_MANIFEST_FILENAME', '.starter-kit.json'),

];
