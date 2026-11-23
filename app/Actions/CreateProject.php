<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\StarterKitRelease;
use Illuminate\Support\Facades\Process;
use Throwable;

final readonly class CreateProject
{
    public function __construct(
        private ApplyStarterKitVariant $applyStarterKitVariant,
        private InstallProject $installProject,
        private DeleteDirectory $deleteDirectory,
    ) {}

    public function handle(StarterKitRelease $release, string $directory, bool $teams): void
    {
        try {
            Process::forever()
                ->run(['composer', 'create-project', $release->kit->package(), $directory, $release->version, '--prefer-dist', '--no-install', '--no-scripts', '--no-interaction'])
                ->throw();

            $this->applyStarterKitVariant->handle($release, $directory, $teams);

            $this->installProject->handle($release, $directory);
        } catch (Throwable $throwable) {
            $this->deleteDirectory->handle($directory);

            throw $throwable;
        }
    }
}
