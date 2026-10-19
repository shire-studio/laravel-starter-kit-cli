<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\Kit;
use App\Exceptions\CouldNotResolveStarterKitRelease;
use Illuminate\Support\Arr;
use InvalidArgumentException;

final readonly class ListStableStarterKitVersions
{
    public function __construct(
        private FetchStarterKitMetadata $fetchStarterKitMetadata,
    ) {}

    /**
     * @return list<string>
     */
    public function handle(Kit $kit): array
    {
        try {
            $versions = Arr::array($this->fetchStarterKitMetadata->handle($kit), 'versions');
        } catch (InvalidArgumentException) {
            throw CouldNotResolveStarterKitRelease::invalidMetadata($kit);
        }

        return array_values(array_filter(
            $versions,
            fn (mixed $version): bool => is_string($version) && preg_match('/^v?\d+\.\d+\.\d+$/', $version) === 1,
        ));
    }
}
