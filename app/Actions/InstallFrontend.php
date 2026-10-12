<?php

declare(strict_types=1);

namespace App\Actions;

use Illuminate\Support\Facades\Process;

final readonly class InstallFrontend
{
    public function handle(string $directory): void
    {
        $commands = [
            ['pnpm', 'install'],
            ['composer', 'transform-types'],
            ['pnpm', 'run', 'build'],
        ];

        foreach ($commands as $command) {
            Process::path($directory)->forever()->run($command)->throw();
        }
    }
}
