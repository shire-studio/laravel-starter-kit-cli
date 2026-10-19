<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\Kit;
use RuntimeException;

final class CouldNotResolveStarterKitRelease extends RuntimeException
{
    public static function noStableVersion(Kit $kit): self
    {
        return new self("No stable release of [{$kit->package()}] could be found.");
    }

    public static function invalidMetadata(Kit $kit): self
    {
        return new self("Composer returned metadata for [{$kit->package()}] that could not be read.");
    }
}
