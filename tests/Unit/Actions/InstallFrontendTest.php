<?php

declare(strict_types=1);

use App\Actions\InstallFrontend;
use App\Enums\Kit;
use Illuminate\Process\Exceptions\ProcessFailedException;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;

it('installs the dependencies, transforms the types and builds the assets', function (): void {
    Process::fake();

    resolve(InstallFrontend::class)->handle(Kit::Vue, '/projects/example-app');

    Process::assertRanInOrder([
        fn (PendingProcess $process): bool => $process->command === ['pnpm', 'install'] && $process->path === '/projects/example-app',
        fn (PendingProcess $process): bool => $process->command === ['composer', 'transform-types'] && $process->path === '/projects/example-app',
        fn (PendingProcess $process): bool => $process->command === ['pnpm', 'run', 'build'] && $process->path === '/projects/example-app',
    ]);
});

it('skips the type generation for kits without generated types', function (): void {
    Process::fake();

    resolve(InstallFrontend::class)->handle(Kit::Livewire, '/projects/example-app');

    Process::assertRanInOrder([
        fn (PendingProcess $process): bool => $process->command === ['pnpm', 'install'],
        fn (PendingProcess $process): bool => $process->command === ['pnpm', 'run', 'build'],
    ]);
    Process::assertDidntRun(['composer', 'transform-types']);
});

it('stops when a command fails', function (): void {
    Process::fake([
        "'pnpm' 'install'" => Process::result(exitCode: 1),
        '*' => Process::result(),
    ]);

    expect(fn () => resolve(InstallFrontend::class)->handle(Kit::Vue, '/projects/example-app'))->toThrow(ProcessFailedException::class);

    Process::assertDidntRun(['pnpm', 'run', 'build']);
});
