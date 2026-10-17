<?php

declare(strict_types=1);

use App\Data\UpdateDirectory;

it('builds the paths inside the update directory', function (): void {
    $updateDirectory = UpdateDirectory::in('/projects/example-app');

    expect($updateDirectory->path())->toBe('/projects/example-app/.starter-kit-update')
        ->and($updateDirectory->prompt())->toBe('/projects/example-app/.starter-kit-update/PROMPT.md')
        ->and($updateDirectory->manifest())->toBe('/projects/example-app/.starter-kit-update/.starter-kit.json')
        ->and($updateDirectory->removedFeatures())->toBe('/projects/example-app/.starter-kit-update/removed.json');
});

it('uses the configured directory and manifest names', function (): void {
    config([
        'starter-kit.update_directory' => '.upstream',
        'starter-kit.manifest_filename' => 'starter-kit.json',
    ]);

    $updateDirectory = UpdateDirectory::in('/projects/example-app');

    expect($updateDirectory->name)->toBe('.upstream')
        ->and($updateDirectory->manifest())->toBe('/projects/example-app/.upstream/starter-kit.json');
});

it('makes paths relative to the project directory', function (): void {
    $updateDirectory = UpdateDirectory::in('/projects/example-app');

    expect($updateDirectory->relative($updateDirectory->detectedVersion()))->toBe('.starter-kit-update/detected-version');
});
