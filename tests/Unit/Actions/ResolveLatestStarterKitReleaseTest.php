<?php

declare(strict_types=1);

use App\Actions\ResolveLatestStarterKitRelease;
use App\Enums\Kit;
use App\Exceptions\CouldNotResolveStarterKitRelease;
use Illuminate\Process\Exceptions\ProcessFailedException;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;

it('resolves the newest of the given stable versions', function (): void {
    Process::fake([
        '*' => Process::result(composerShowOutput(['v1.4.0'], 'c31ae68197d092fbaac6ac68712b44f230e27850')),
    ]);

    $release = resolve(ResolveLatestStarterKitRelease::class)->handle(Kit::Vue, ['v1.4.0', 'v1.3.10']);

    expect($release)
        ->kit->toBe(Kit::Vue)
        ->version->toBe('v1.4.0')
        ->reference->toBe('c31ae68197d092fbaac6ac68712b44f230e27850')
        ->repository->toBe('https://github.com/shire-studio/laravel-starter-kit-vue.git');

    Process::assertRanTimes(fn (PendingProcess $process): bool => true, 1);
    Process::assertRan(['composer', 'show', 'shire-studio/laravel-starter-kit-vue', 'v1.4.0', '--all', '--format=json']);
});

it('throws when there is no stable release', function (): void {
    Process::fake();

    resolve(ResolveLatestStarterKitRelease::class)->handle(Kit::Vue, []);
})->throws(CouldNotResolveStarterKitRelease::class, 'No stable release of [shire-studio/laravel-starter-kit-vue] could be found.');

it('throws when composer cannot find the package', function (): void {
    Process::fake([
        '*' => Process::result(errorOutput: 'Package "shire-studio/laravel-starter-kit-vue" not found.', exitCode: 1),
    ]);

    resolve(ResolveLatestStarterKitRelease::class)->handle(Kit::Vue, ['v1.4.0']);
})->throws(ProcessFailedException::class);

it('resolves the release of the given kit', function (): void {
    Process::fake([
        '*' => Process::result(composerShowOutput(['v2.0.0'])),
    ]);

    $release = resolve(ResolveLatestStarterKitRelease::class)->handle(Kit::React, ['v2.0.0']);

    expect($release)
        ->kit->toBe(Kit::React)
        ->version->toBe('v2.0.0');

    Process::assertRan(['composer', 'show', 'shire-studio/laravel-starter-kit-react', 'v2.0.0', '--all', '--format=json']);
});

it('runs composer outside the current project so its repositories are ignored', function (): void {
    Process::fake([
        '*' => Process::result(composerShowOutput(['v1.4.0'])),
    ]);

    resolve(ResolveLatestStarterKitRelease::class)->handle(Kit::Vue, ['v1.4.0']);

    Process::assertRanTimes(fn (PendingProcess $process): bool => $process->path === sys_get_temp_dir(), 1);
});

it('throws when composer returns output that is not json', function (): void {
    Process::fake([
        '*' => Process::result('Composer could not reach the repository.'),
    ]);

    resolve(ResolveLatestStarterKitRelease::class)->handle(Kit::Vue, ['v1.4.0']);
})->throws(CouldNotResolveStarterKitRelease::class, 'Composer returned metadata for [shire-studio/laravel-starter-kit-vue] that could not be read.');

it('throws when composer returns json that is not an object', function (): void {
    Process::fake([
        '*' => Process::result('"shire-studio/laravel-starter-kit-vue"'),
    ]);

    resolve(ResolveLatestStarterKitRelease::class)->handle(Kit::Vue, ['v1.4.0']);
})->throws(CouldNotResolveStarterKitRelease::class, 'Composer returned metadata for [shire-studio/laravel-starter-kit-vue] that could not be read.');

it('throws when the release metadata has no source', function (): void {
    Process::fake([
        '*' => Process::result((string) json_encode(['versions' => ['v1.4.0']])),
    ]);

    resolve(ResolveLatestStarterKitRelease::class)->handle(Kit::Vue, ['v1.4.0']);
})->throws(CouldNotResolveStarterKitRelease::class, 'Composer returned metadata for [shire-studio/laravel-starter-kit-vue] that could not be read.');
