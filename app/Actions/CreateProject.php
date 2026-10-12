<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\StarterKitRelease;
use Illuminate\Support\Facades\Process;

final readonly class CreateProject
{
    public function handle(StarterKitRelease $release, string $directory): void
    {
        Process::forever()
            ->run(['composer', 'create-project', $release->package, $directory, $release->version, '--prefer-dist', '--no-interaction'])
            ->throw();
    }
}
