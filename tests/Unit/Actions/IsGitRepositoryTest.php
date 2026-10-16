<?php

declare(strict_types=1);

use App\Actions\IsGitRepository;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;

it('detects a git repository', function (): void {
    Process::fake([
        '*' => Process::result("true\n"),
    ]);

    expect(resolve(IsGitRepository::class)->handle('/projects/example-app'))->toBeTrue();

    Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['git', 'rev-parse', '--is-inside-work-tree'] && $process->path === '/projects/example-app');
});

it('does not treat a directory outside git as a repository', function (): void {
    Process::fake([
        '*' => Process::result(errorOutput: 'fatal: not a git repository', exitCode: 128),
    ]);

    expect(resolve(IsGitRepository::class)->handle('/projects/example-app'))->toBeFalse();
});
