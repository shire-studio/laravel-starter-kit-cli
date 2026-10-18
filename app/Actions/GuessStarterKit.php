<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\Kit;
use Illuminate\Support\Facades\File;

final readonly class GuessStarterKit
{
    public function handle(string $directory): ?Kit
    {
        $path = "{$directory}/composer.json";

        if (File::missing($path)) {
            return null;
        }

        $composer = json_decode(File::get($path), true);

        if (! is_array($composer)) {
            return null;
        }

        $name = $composer['name'] ?? null;

        if (! is_string($name)) {
            return null;
        }

        return Kit::fromPackage($name);
    }
}
