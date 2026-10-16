<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\StarterKitRelease;
use App\Enums\Agent;
use Illuminate\Container\Attributes\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;

final readonly class DetectStarterKitVersion
{
    public function __construct(
        private DeleteDirectory $deleteDirectory,
        private LaunchAgent $launchAgent,
        #[Config('starter-kit.update_directory')]
        private string $updateDirectoryName,
        #[Config('starter-kit.manifest_filename')]
        private string $manifestFilename,
    ) {}

    /**
     * @param  list<string>  $versions
     */
    public function handle(Agent $agent, string $projectDirectory, StarterKitRelease $latestRelease, array $versions): ?string
    {
        $updateDirectory = "{$projectDirectory}/{$this->updateDirectoryName}";
        $detectedVersionPath = "{$updateDirectory}/detected-version";

        $this->deleteDirectory->handle($updateDirectory);

        File::ensureDirectoryExists($updateDirectory);

        File::put("{$updateDirectory}/DETECT.md", View::make('prompts.detect', [
            'release' => $latestRelease,
            'versions' => $versions,
            'updateDirectory' => $this->updateDirectoryName,
            'manifestFilename' => $this->manifestFilename,
        ])->render());

        $this->launchAgent->handle($agent, $projectDirectory, "Read {$this->updateDirectoryName}/DETECT.md and follow the instructions in it.");

        if (File::missing($detectedVersionPath)) {
            return null;
        }

        return mb_trim(File::get($detectedVersionPath));
    }
}
