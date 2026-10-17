<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class CouldNotReadStarterKitManifest extends RuntimeException
{
    public static function invalid(string $filename): self
    {
        return new self("The [{$filename}] file could not be read. Fix or delete it, then run update again.");
    }
}
