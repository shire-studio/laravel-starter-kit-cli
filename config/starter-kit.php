<?php

declare(strict_types=1);

return [

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

    /*
    |--------------------------------------------------------------------------
    | Update Directory
    |--------------------------------------------------------------------------
    |
    | The directory, relative to the project root, that the update command
    | fills with the upstream diff, file snapshots and the agent prompt.
    | It is excluded from git and removed once the update is complete.
    |
    */

    'update_directory' => env('STARTER_KIT_UPDATE_DIRECTORY', '.starter-kit-update'),

    /*
    |--------------------------------------------------------------------------
    | Overlay Directory
    |--------------------------------------------------------------------------
    |
    | The directory, relative to the root of a starter kit release, that holds
    | the overlays for its variants, such as teams. The CLI applies or drops
    | them when it creates a project or compares two starter kit releases.
    | Starter kit releases fix this path, so it has no environment override.
    |
    */

    'overlay_directory' => '.starter-kit',

];
