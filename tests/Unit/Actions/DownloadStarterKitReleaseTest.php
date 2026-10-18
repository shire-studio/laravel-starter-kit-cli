<?php

declare(strict_types=1);

use App\Actions\DownloadStarterKitRelease;
use Illuminate\Process\Exceptions\ProcessFailedException;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;

it('downloads the release without installing or running scripts', function (): void {
    Process::fake();

    resolve(DownloadStarterKitRelease::class)->handle(starterKitRelease(), '/tmp/starter-kit/new');

    Process::assertRan(fn (PendingProcess $process): bool => $process->command === [
        'composer',
        'create-project',
        'shire-studio/laravel-starter-kit-vue',
        '/tmp/starter-kit/new',
        'v1.4.0',
        '--prefer-dist',
        '--no-install',
        '--no-scripts',
        '--no-plugins',
        '--no-interaction',
    ] && $process->path === sys_get_temp_dir());
});

it('throws when composer fails', function (): void {
    Process::fake([
        '*' => Process::result(exitCode: 1),
    ]);

    resolve(DownloadStarterKitRelease::class)->handle(starterKitRelease(), '/tmp/starter-kit/new');
})->throws(ProcessFailedException::class);
