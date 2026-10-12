<?php

declare(strict_types=1);

use App\Actions\InstallFrontend;
use Illuminate\Process\Exceptions\ProcessFailedException;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;

it('installs the dependencies, transforms the types and builds the assets', function (): void {
    Process::fake();

    new InstallFrontend()->handle('/projects/example-app');

    Process::assertRanInOrder([
        fn (PendingProcess $process): bool => $process->command === ['pnpm', 'install'] && $process->path === '/projects/example-app',
        fn (PendingProcess $process): bool => $process->command === ['composer', 'transform-types'] && $process->path === '/projects/example-app',
        fn (PendingProcess $process): bool => $process->command === ['pnpm', 'run', 'build'] && $process->path === '/projects/example-app',
    ]);
});

it('stops when a command fails', function (): void {
    Process::fake([
        "'pnpm' 'install'" => Process::result(exitCode: 1),
        '*' => Process::result(),
    ]);

    expect(fn () => new InstallFrontend()->handle('/projects/example-app'))->toThrow(ProcessFailedException::class);

    Process::assertDidntRun(['pnpm', 'run', 'build']);
});
