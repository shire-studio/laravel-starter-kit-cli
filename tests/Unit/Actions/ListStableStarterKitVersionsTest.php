<?php

declare(strict_types=1);

use App\Actions\ListStableStarterKitVersions;
use App\Enums\Kit;
use App\Exceptions\CouldNotResolveStarterKitRelease;
use Illuminate\Support\Facades\Process;

it('lists the stable versions newest first', function (): void {
    Process::fake([
        '*' => Process::result(composerShowOutput(['v2.0.0-beta1', 'v1.4.0', 'v1.3.10', 'dev-main'])),
    ]);

    expect(resolve(ListStableStarterKitVersions::class)->handle(Kit::Vue))->toBe(['v1.4.0', 'v1.3.10']);

    Process::assertRan(['composer', 'show', 'shire-studio/laravel-starter-kit-vue', '--all', '--format=json']);
});

it('throws when the metadata has no versions', function (): void {
    Process::fake([
        '*' => Process::result((string) json_encode(['name' => 'shire-studio/laravel-starter-kit-vue'])),
    ]);

    resolve(ListStableStarterKitVersions::class)->handle(Kit::Vue);
})->throws(CouldNotResolveStarterKitRelease::class, 'Composer returned metadata for [shire-studio/laravel-starter-kit-vue] that could not be read.');
