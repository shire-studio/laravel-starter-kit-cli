<?php

declare(strict_types=1);

namespace App\Actions;

use App\Exceptions\CouldNotResolveStarterKitRelease;
use Illuminate\Container\Attributes\Config;
use Illuminate\Support\Arr;
use InvalidArgumentException;

final readonly class ListStableStarterKitVersions
{
    public function __construct(
        private FetchStarterKitMetadata $fetchStarterKitMetadata,
        #[Config('starter-kit.package')]
        private string $package,
    ) {}

    /**
     * @return list<string>
     */
    public function handle(): array
    {
        try {
            $versions = Arr::array($this->fetchStarterKitMetadata->handle(), 'versions');
        } catch (InvalidArgumentException) {
            throw CouldNotResolveStarterKitRelease::invalidMetadata($this->package);
        }

        return array_values(array_filter(
            $versions,
            fn (mixed $version): bool => is_string($version) && preg_match('/^v?\d+\.\d+\.\d+$/', $version) === 1,
        ));
    }
}
