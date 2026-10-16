<?php

declare(strict_types=1);

use App\Actions\ResolveStarterKitRelease;
use Illuminate\Process\Exceptions\ProcessFailedException;
use Illuminate\Support\Facades\Process;

it('resolves the given version', function (): void {
    Process::fake([
        '*' => Process::result(composerShowOutput(['v1.3.0'], 'a1b2c3')),
    ]);

    $release = resolve(ResolveStarterKitRelease::class)->handle('v1.3.0');

    expect($release)->toEqual(starterKitRelease('v1.3.0', 'a1b2c3'));

    Process::assertRan(['composer', 'show', 'shire-studio/laravel-starter-kit', 'v1.3.0', '--all', '--format=json']);
});

it('throws when composer cannot find the version', function (): void {
    Process::fake([
        '*' => Process::result(errorOutput: 'Package "shire-studio/laravel-starter-kit" with version "v9.9.9" not found.', exitCode: 1),
    ]);

    resolve(ResolveStarterKitRelease::class)->handle('v9.9.9');
})->throws(ProcessFailedException::class);
