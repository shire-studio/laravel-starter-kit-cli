<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\StarterKitManifest;
use Illuminate\Container\Attributes\Config;
use Illuminate\Support\Facades\File;

final readonly class RecordStarterKitRelease
{
    public function __construct(
        #[Config('starter-kit.manifest_filename')]
        private string $manifestFilename,
    ) {}

    public function handle(StarterKitManifest $manifest, string $directory): void
    {
        $release = $manifest->release;

        $contents = json_encode([
            'package' => $release->kit->package(),
            'version' => $release->version,
            ...($release->reference === null ? [] : ['reference' => $release->reference]),
            'repository' => $release->repository,
            'teams' => $manifest->teams,
            ...($manifest->removedFeatures === [] ? [] : ['removed' => $manifest->removedFeatures]),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        File::put("{$directory}/{$this->manifestFilename}", "{$contents}\n");
    }
}
