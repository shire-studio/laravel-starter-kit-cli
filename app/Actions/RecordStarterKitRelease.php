<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\StarterKitRelease;
use Illuminate\Container\Attributes\Config;
use Illuminate\Support\Facades\File;

final readonly class RecordStarterKitRelease
{
    public function __construct(
        #[Config('starter-kit.manifest_filename')]
        private string $manifestFilename,
    ) {}

    public function handle(StarterKitRelease $release, string $directory): void
    {
        $manifest = json_encode([
            'package' => $release->package,
            'version' => $release->version,
            'reference' => $release->reference,
            'repository' => $release->repository,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        File::put("{$directory}/{$this->manifestFilename}", "{$manifest}\n");
    }
}
