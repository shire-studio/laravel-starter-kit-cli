<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\StarterKitRelease;
use App\Enums\Kit;
use App\Exceptions\CouldNotResolveStarterKitRelease;
use Illuminate\Support\Arr;
use InvalidArgumentException;

final readonly class ResolveStarterKitRelease
{
    public function __construct(
        private FetchStarterKitMetadata $fetchStarterKitMetadata,
    ) {}

    public function handle(Kit $kit, string $version): StarterKitRelease
    {
        $metadata = $this->fetchStarterKitMetadata->handle($kit, $version);

        try {
            return new StarterKitRelease(
                kit: $kit,
                version: $version,
                reference: Arr::string($metadata, 'source.reference'),
                repository: Arr::string($metadata, 'source.url'),
            );
        } catch (InvalidArgumentException) {
            throw CouldNotResolveStarterKitRelease::invalidMetadata($kit);
        }
    }
}
