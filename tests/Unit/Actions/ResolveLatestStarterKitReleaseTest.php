<?php

declare(strict_types=1);

use App\Actions\ResolveLatestStarterKitRelease;
use App\Exceptions\CouldNotResolveStarterKitRelease;
use Illuminate\Process\Exceptions\ProcessFailedException;
use Illuminate\Support\Facades\Process;

it('resolves the latest stable release', function (): void {
    Process::fake([
        "*'v1.4.0'*" => Process::result(composerShowOutput(['v1.4.0'], 'c31ae68197d092fbaac6ac68712b44f230e27850')),
        '*' => Process::result(composerShowOutput(['v2.0.0-beta1', 'v1.4.0', 'v1.3.10', 'dev-main'], 'not-the-stable-reference')),
    ]);

    $release = resolve(ResolveLatestStarterKitRelease::class)->handle();

    expect($release)
        ->package->toBe('shire-studio/laravel-starter-kit')
        ->version->toBe('v1.4.0')
        ->reference->toBe('c31ae68197d092fbaac6ac68712b44f230e27850')
        ->repository->toBe('https://github.com/shire-studio/laravel-starter-kit.git');

    Process::assertRanInOrder([
        ['composer', 'show', 'shire-studio/laravel-starter-kit', '--all', '--format=json'],
        ['composer', 'show', 'shire-studio/laravel-starter-kit', 'v1.4.0', '--all', '--format=json'],
    ]);
});

it('throws when there is no stable release', function (): void {
    Process::fake([
        '*' => Process::result(composerShowOutput(['v2.0.0-beta1', 'dev-main'])),
    ]);

    resolve(ResolveLatestStarterKitRelease::class)->handle();
})->throws(CouldNotResolveStarterKitRelease::class, 'No stable release of [shire-studio/laravel-starter-kit] could be found.');

it('throws when composer cannot find the package', function (): void {
    Process::fake([
        '*' => Process::result(errorOutput: 'Package "shire-studio/laravel-starter-kit" not found.', exitCode: 1),
    ]);

    resolve(ResolveLatestStarterKitRelease::class)->handle();
})->throws(ProcessFailedException::class);
