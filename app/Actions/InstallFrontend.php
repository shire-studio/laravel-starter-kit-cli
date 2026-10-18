<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\Kit;
use Illuminate\Support\Facades\Process;

final readonly class InstallFrontend
{
    public function handle(Kit $kit, string $directory): void
    {
        $commands = [
            ['pnpm', 'install'],
            ...($kit->generatesTypes() ? [['composer', 'transform-types']] : []),
            ['pnpm', 'run', 'build'],
        ];

        foreach ($commands as $command) {
            Process::path($directory)->forever()->run($command)->throw();
        }
    }
}
