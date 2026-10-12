<?php

declare(strict_types=1);

namespace App\Actions;

use Illuminate\Support\Facades\Process;

final readonly class InitialiseGitRepository
{
    public function handle(string $directory, string $message): void
    {
        $commands = [
            ['git', 'init', '--quiet', '--initial-branch=main'],
            ['git', 'add', '--all'],
            ['git', 'commit', '--quiet', "--message={$message}"],
        ];

        foreach ($commands as $command) {
            Process::path($directory)->run($command)->throw();
        }
    }
}
