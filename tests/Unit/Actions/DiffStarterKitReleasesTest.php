<?php

declare(strict_types=1);

use App\Actions\DiffStarterKitReleases;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->directory = temporaryDirectory();

    createFiles("{$this->directory}/old", [
        'composer.json' => "{\"version\": 1}\n",
        'composer.lock' => "{\"hash\": 1}\n",
        'config/app.php' => "<?php return [];\n",
        'config/legacy.php' => "<?php return ['legacy' => true];\n",
        'routes/web.php' => "<?php // version 1\n",
    ]);
});

afterEach(function (): void {
    File::deleteDirectory($this->directory);
});

it('diffs two releases', function (): void {
    createFiles("{$this->directory}/new", [
        'composer.json' => "{\"version\": 2}\n",
        'composer.lock' => "{\"hash\": 1}\n",
        'config/app.php' => "<?php return [];\n",
        'routes/web.php' => "<?php // version 2\n",
        'app/Actions/NewAction.php' => "<?php // new\n",
    ]);

    $diff = resolve(DiffStarterKitReleases::class)->handle("{$this->directory}/old", "{$this->directory}/new", "{$this->directory}/diff.git");

    expect($diff->addedFiles)->toBe(['app/Actions/NewAction.php'])
        ->and($diff->modifiedFiles)->toBe(['composer.json', 'routes/web.php'])
        ->and($diff->deletedFiles)->toBe(['config/legacy.php'])
        ->and($diff->changedFileCount())->toBe(4)
        ->and($diff->hasChanges())->toBeTrue()
        ->and($diff->patch)
        ->toContain('diff --git a/routes/web.php b/routes/web.php')
        ->toContain('-<?php // version 1')
        ->toContain('+<?php // version 2')
        ->toContain('+++ b/app/Actions/NewAction.php')
        ->toContain('--- a/config/legacy.php')
        ->not->toContain('config/app.php');
});

it('leaves lock files out of the changes', function (): void {
    createFiles("{$this->directory}/new", [
        'composer.json' => "{\"version\": 1}\n",
        'composer.lock' => "{\"hash\": 2}\n",
        'config/app.php' => "<?php return [];\n",
        'config/legacy.php' => "<?php return ['legacy' => true];\n",
        'routes/web.php' => "<?php // version 1\n",
        'pnpm-lock.yaml' => "lockfileVersion: '9.0'\n",
    ]);

    $diff = resolve(DiffStarterKitReleases::class)->handle("{$this->directory}/old", "{$this->directory}/new", "{$this->directory}/diff.git");

    expect($diff->patch)->toBeEmpty()
        ->and($diff->hasChanges())->toBeFalse()
        ->and($diff->modifiedFiles)->toBeEmpty()
        ->and($diff->addedFiles)->toBeEmpty();
});

it('leaves the release manifest out of the changes', function (): void {
    File::copyDirectory("{$this->directory}/old", "{$this->directory}/new");

    createFiles("{$this->directory}/old", ['.starter-kit.json' => "{\"version\": \"v1.4.0\"}\n"]);
    createFiles("{$this->directory}/new", ['.starter-kit.json' => "{\"version\": \"v1.5.0\"}\n"]);

    $diff = resolve(DiffStarterKitReleases::class)->handle("{$this->directory}/old", "{$this->directory}/new", "{$this->directory}/diff.git");

    expect($diff->hasChanges())->toBeFalse()
        ->and($diff->patch)->toBeEmpty();
});

it('finds no changes between identical releases', function (): void {
    File::copyDirectory("{$this->directory}/old", "{$this->directory}/new");

    $diff = resolve(DiffStarterKitReleases::class)->handle("{$this->directory}/old", "{$this->directory}/new", "{$this->directory}/diff.git");

    expect($diff->hasChanges())->toBeFalse()
        ->and($diff->changedFileCount())->toBe(0);
});

it('includes files the release itself ignores', function (): void {
    File::copyDirectory("{$this->directory}/old", "{$this->directory}/new");

    createFiles("{$this->directory}/new", [
        '.gitignore' => "*.sqlite\n",
        'database/database.sqlite' => 'sqlite',
    ]);

    $diff = resolve(DiffStarterKitReleases::class)->handle("{$this->directory}/old", "{$this->directory}/new", "{$this->directory}/diff.git");

    expect($diff->addedFiles)->toBe(['.gitignore', 'database/database.sqlite']);
});
