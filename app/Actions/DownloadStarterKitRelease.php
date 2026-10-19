<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\StarterKitRelease;
use Illuminate\Support\Facades\Process;

final readonly class DownloadStarterKitRelease
{
    public function handle(StarterKitRelease $release, string $directory): void
    {
        Process::path(sys_get_temp_dir())
            ->forever()
            ->run([
                'composer',
                'create-project',
                $release->kit->package(),
                $directory,
                $release->version,
                '--prefer-dist',
                '--no-install',
                '--no-scripts',
                '--no-plugins',
                '--no-interaction',
            ])
            ->throw();
    }
}
