<?php

declare(strict_types=1);

namespace App\Actions;

use Illuminate\Container\Attributes\Config;
use Illuminate\Support\Facades\File;

final readonly class ReadDetectedStarterKitVersion
{
    public function __construct(
        #[Config('starter-kit.update_directory')]
        private string $updateDirectoryName,
    ) {}

    public function handle(string $projectDirectory): ?string
    {
        $path = "{$projectDirectory}/{$this->updateDirectoryName}/detected-version";

        if (File::missing($path)) {
            return null;
        }

        return mb_trim(File::get($path));
    }
}
