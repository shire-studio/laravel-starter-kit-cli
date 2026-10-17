<?php

declare(strict_types=1);

use App\Actions\ResolveLatestStarterKitRelease;
use App\Exceptions\CouldNotResolveStarterKitRelease;
use Illuminate\Process\Exceptions\ProcessFailedException;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;

it('resolves the newest of the given stable versions', function (): void {
    Process::fake([
        '*' => Process::result(composerShowOutput(['v1.4.0'], 'c31ae68197d092fbaac6ac68712b44f230e27850')),
    ]);

    $release = resolve(ResolveLatestStarterKitRelease::class)->handle(['v1.4.0', 'v1.3.10']);

    expect($release)
        ->package->toBe('shire-studio/laravel-starter-kit')
        ->version->toBe('v1.4.0')
        ->reference->toBe('c31ae68197d092fbaac6ac68712b44f230e27850')
        ->repository->toBe('https://github.com/shire-studio/laravel-starter-kit.git');

    Process::assertRanTimes(fn (PendingProcess $process): bool => true, 1);
    Process::assertRan(['composer', 'show', 'shire-studio/laravel-starter-kit', 'v1.4.0', '--all', '--format=json']);
});

it('throws when there is no stable release', function (): void {
    Process::fake();

    resolve(ResolveLatestStarterKitRelease::class)->handle([]);
})->throws(CouldNotResolveStarterKitRelease::class, 'No stable release of [shire-studio/laravel-starter-kit] could be found.');

it('throws when composer cannot find the package', function (): void {
    Process::fake([
        '*' => Process::result(errorOutput: 'Package "shire-studio/laravel-starter-kit" not found.', exitCode: 1),
    ]);

    resolve(ResolveLatestStarterKitRelease::class)->handle(['v1.4.0']);
})->throws(ProcessFailedException::class);

it('resolves the release of the configured package', function (): void {
    config(['starter-kit.package' => 'acme/starter-kit']);

    Process::fake([
        '*' => Process::result(composerShowOutput(['v2.0.0'])),
    ]);

    $release = resolve(ResolveLatestStarterKitRelease::class)->handle(['v2.0.0']);

    expect($release)
        ->package->toBe('acme/starter-kit')
        ->version->toBe('v2.0.0');

    Process::assertRan(['composer', 'show', 'acme/starter-kit', 'v2.0.0', '--all', '--format=json']);
});

it('runs composer outside the current project so its repositories are ignored', function (): void {
    Process::fake([
        '*' => Process::result(composerShowOutput(['v1.4.0'])),
    ]);

    resolve(ResolveLatestStarterKitRelease::class)->handle(['v1.4.0']);

    Process::assertRanTimes(fn (PendingProcess $process): bool => $process->path === sys_get_temp_dir(), 1);
});

it('throws when composer returns output that is not json', function (): void {
    Process::fake([
        '*' => Process::result('Composer could not reach the repository.'),
    ]);

    resolve(ResolveLatestStarterKitRelease::class)->handle(['v1.4.0']);
})->throws(CouldNotResolveStarterKitRelease::class, 'Composer returned metadata for [shire-studio/laravel-starter-kit] that could not be read.');

it('throws when composer returns json that is not an object', function (): void {
    Process::fake([
        '*' => Process::result('"shire-studio/laravel-starter-kit"'),
    ]);

    resolve(ResolveLatestStarterKitRelease::class)->handle(['v1.4.0']);
})->throws(CouldNotResolveStarterKitRelease::class, 'Composer returned metadata for [shire-studio/laravel-starter-kit] that could not be read.');

it('throws when the release metadata has no source', function (): void {
    Process::fake([
        '*' => Process::result((string) json_encode(['versions' => ['v1.4.0']])),
    ]);

    resolve(ResolveLatestStarterKitRelease::class)->handle(['v1.4.0']);
})->throws(CouldNotResolveStarterKitRelease::class, 'Composer returned metadata for [shire-studio/laravel-starter-kit] that could not be read.');
