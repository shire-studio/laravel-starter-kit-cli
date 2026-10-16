<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\StarterKitManifest;
use Illuminate\Container\Attributes\Config;
use Illuminate\Support\Facades\File;

final readonly class ReadProposedRemovedFeatures
{
    public function __construct(
        #[Config('starter-kit.update_directory')]
        private string $updateDirectoryName,
    ) {}

    /**
     * @return list<string>
     */
    public function handle(StarterKitManifest $manifest, string $projectDirectory): array
    {
        $path = "{$projectDirectory}/{$this->updateDirectoryName}/removed.json";

        if (File::missing($path)) {
            return [];
        }

        $proposedRemovedFeatures = json_decode(File::get($path), true);

        if (! is_array($proposedRemovedFeatures)) {
            return [];
        }

        $removedFeatures = array_map(
            trim(...),
            array_filter($proposedRemovedFeatures, is_string(...)),
        );

        return array_values(array_unique(array_filter(
            $removedFeatures,
            fn (string $removedFeature): bool => $removedFeature !== '' && ! in_array($removedFeature, $manifest->removedFeatures, true),
        )));
    }
}
