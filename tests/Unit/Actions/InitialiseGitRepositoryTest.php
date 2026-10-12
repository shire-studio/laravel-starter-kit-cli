<?php

declare(strict_types=1);

use App\Actions\InitialiseGitRepository;
use Illuminate\Process\Exceptions\ProcessFailedException;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;

it('initialises a repository and commits every file', function (): void {
    Process::fake();

    new InitialiseGitRepository()->handle('/projects/example-app', 'chore: initial commit');

    Process::assertRanInOrder([
        fn (PendingProcess $process): bool => $process->command === ['git', 'init', '--quiet', '--initial-branch=main'] && $process->path === '/projects/example-app',
        fn (PendingProcess $process): bool => $process->command === ['git', 'add', '--all'] && $process->path === '/projects/example-app',
        fn (PendingProcess $process): bool => $process->command === ['git', 'commit', '--quiet', '--message=chore: initial commit'] && $process->path === '/projects/example-app',
    ]);
});

it('throws when git fails', function (): void {
    Process::fake([
        "'git' 'commit'*" => Process::result(errorOutput: 'Please tell me who you are.', exitCode: 128),
        '*' => Process::result(),
    ]);

    new InitialiseGitRepository()->handle('/projects/example-app', 'chore: initial commit');
})->throws(ProcessFailedException::class);
