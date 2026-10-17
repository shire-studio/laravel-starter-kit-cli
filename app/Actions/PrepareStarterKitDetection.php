<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\StarterKitRelease;
use Illuminate\Container\Attributes\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;

final readonly class PrepareStarterKitDetection
{
    public function __construct(
        private DeleteDirectory $deleteDirectory,
        #[Config('starter-kit.update_directory')]
        private string $updateDirectoryName,
        #[Config('starter-kit.manifest_filename')]
        private string $manifestFilename,
    ) {}

    /**
     * @param  list<string>  $versions
     */
    public function handle(string $projectDirectory, StarterKitRelease $latestRelease, array $versions): void
    {
        $updateDirectory = "{$projectDirectory}/{$this->updateDirectoryName}";

        $this->deleteDirectory->handle($updateDirectory);

        File::ensureDirectoryExists($updateDirectory);

        File::put("{$updateDirectory}/DETECT.md", View::make('prompts.detect', [
            'release' => $latestRelease,
            'versions' => $versions,
            'updateDirectory' => $this->updateDirectoryName,
            'manifestFilename' => $this->manifestFilename,
        ])->render());
    }
}
