<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class CouldNotCompleteStarterKitUpdate extends RuntimeException
{
    public static function missingManifest(string $path): self
    {
        return new self("The staged [{$path}] could not be found, so the project was not marked as updated. Run update again.");
    }
}
