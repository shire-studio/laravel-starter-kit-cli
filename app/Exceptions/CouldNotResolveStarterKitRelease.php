<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class CouldNotResolveStarterKitRelease extends RuntimeException
{
    public static function noStableVersion(string $package): self
    {
        return new self("No stable release of [{$package}] could be found.");
    }

    public static function invalidMetadata(string $package): self
    {
        return new self("Composer returned metadata for [{$package}] that could not be read.");
    }
}
