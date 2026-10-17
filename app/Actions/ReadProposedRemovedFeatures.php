<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\StarterKitManifest;
use App\Data\UpdateDirectory;
use Illuminate\Support\Facades\File;

final readonly class ReadProposedRemovedFeatures
{
    /**
     * @return list<string>
     */
    public function handle(StarterKitManifest $manifest, UpdateDirectory $updateDirectory): array
    {
        if (File::missing($updateDirectory->removedFeatures())) {
            return [];
        }

        $proposedRemovedFeatures = json_decode(File::get($updateDirectory->removedFeatures()), true);

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
