<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\StarterKitManifest;
use App\Data\StarterKitRelease;
use Illuminate\Container\Attributes\Config;

final readonly class CompleteStarterKitUpdate
{
    public function __construct(
        private RecordStarterKitRelease $recordStarterKitRelease,
        private DeleteDirectory $deleteDirectory,
        #[Config('starter-kit.update_directory')]
        private string $updateDirectoryName,
    ) {}

    /**
     * @param  list<string>  $newRemovedFeatures
     */
    public function handle(StarterKitManifest $manifest, StarterKitRelease $release, string $projectDirectory, array $newRemovedFeatures = []): void
    {
        $this->recordStarterKitRelease->handle($release, $projectDirectory, [...$manifest->removedFeatures, ...$newRemovedFeatures]);

        $this->deleteDirectory->handle("{$projectDirectory}/{$this->updateDirectoryName}");
    }
}
