<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\Kit;
use Illuminate\Support\Arr;
use RuntimeException;

final class CouldNotReadStarterKitManifest extends RuntimeException
{
    public static function invalid(string $filename): self
    {
        return new self("The [{$filename}] file could not be read. Fix or delete it, then run update again.");
    }

    public static function unknownPackage(string $filename, string $package): self
    {
        $packageList = Arr::join(array_map(fn (Kit $kit): string => $kit->package(), Kit::cases()), ', ', ' or ');

        return new self("The [{$filename}] file names [{$package}], which is not a Shire Studio starter kit. Set its package to {$packageList}, then run update again.");
    }
}
