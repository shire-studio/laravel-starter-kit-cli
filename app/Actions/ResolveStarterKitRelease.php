<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\StarterKitRelease;
use App\Exceptions\CouldNotResolveStarterKitRelease;
use Illuminate\Container\Attributes\Config;
use Illuminate\Support\Arr;
use InvalidArgumentException;

final readonly class ResolveStarterKitRelease
{
    public function __construct(
        private FetchStarterKitMetadata $fetchStarterKitMetadata,
        #[Config('starter-kit.package')]
        private string $package,
    ) {}

    public function handle(string $version): StarterKitRelease
    {
        $metadata = $this->fetchStarterKitMetadata->handle($version);

        try {
            return new StarterKitRelease(
                package: $this->package,
                version: $version,
                reference: Arr::string($metadata, 'source.reference'),
                repository: Arr::string($metadata, 'source.url'),
            );
        } catch (InvalidArgumentException) {
            throw CouldNotResolveStarterKitRelease::invalidMetadata($this->package);
        }
    }
}
