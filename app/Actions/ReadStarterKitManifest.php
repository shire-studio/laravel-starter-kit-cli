<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\StarterKitManifest;
use App\Data\StarterKitRelease;
use App\Exceptions\CouldNotReadStarterKitManifest;
use Illuminate\Container\Attributes\Config;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use JsonException;

final readonly class ReadStarterKitManifest
{
    public function __construct(
        #[Config('starter-kit.manifest_filename')]
        private string $manifestFilename,
    ) {}

    public function handle(string $directory): ?StarterKitManifest
    {
        $path = "{$directory}/{$this->manifestFilename}";

        if (File::missing($path)) {
            return null;
        }

        try {
            $manifest = json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR);

            if (! is_array($manifest)) {
                throw CouldNotReadStarterKitManifest::invalid($this->manifestFilename);
            }

            return new StarterKitManifest(
                release: new StarterKitRelease(
                    package: Arr::string($manifest, 'package'),
                    version: Arr::string($manifest, 'version'),
                    reference: Arr::string($manifest, 'reference'),
                    repository: Arr::string($manifest, 'repository'),
                ),
                removedFeatures: $this->removedFeatures($manifest),
            );
        } catch (JsonException|InvalidArgumentException) {
            throw CouldNotReadStarterKitManifest::invalid($this->manifestFilename);
        }
    }

    /**
     * @param  array<array-key, mixed>  $manifest
     * @return list<string>
     */
    private function removedFeatures(array $manifest): array
    {
        $removedFeatures = [];

        foreach (Arr::array($manifest, 'removed', []) as $removedFeature) {
            if (! is_string($removedFeature)) {
                throw CouldNotReadStarterKitManifest::invalid($this->manifestFilename);
            }

            $removedFeatures[] = $removedFeature;
        }

        return $removedFeatures;
    }
}
