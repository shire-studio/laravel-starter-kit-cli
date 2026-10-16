<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\StarterKitManifest;
use App\Data\StarterKitRelease;
use App\Data\StarterKitUpdate;
use App\Enums\Agent;
use Illuminate\Container\Attributes\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

final readonly class PrepareStarterKitUpdate
{
    public function __construct(
        private DownloadStarterKitRelease $downloadStarterKitRelease,
        private DiffStarterKitReleases $diffStarterKitReleases,
        private FindRemovedStarterKitFiles $findRemovedStarterKitFiles,
        private FetchStarterKitCommitLog $fetchStarterKitCommitLog,
        private DeleteDirectory $deleteDirectory,
        private RecordStarterKitRelease $recordStarterKitRelease,
        #[Config('starter-kit.update_directory')]
        private string $updateDirectoryName,
        #[Config('starter-kit.manifest_filename')]
        private string $manifestFilename,
    ) {}

    public function handle(StarterKitManifest $manifest, StarterKitRelease $release, string $projectDirectory, ?Agent $agent): StarterKitUpdate
    {
        $temporaryDirectory = sys_get_temp_dir();
        $randomName = Str::random(16);

        $workingDirectory = "{$temporaryDirectory}/laravel-starter-kit-cli/{$randomName}";
        $updateDirectory = "{$projectDirectory}/{$this->updateDirectoryName}";

        try {
            $this->downloadStarterKitRelease->handle($manifest->release, "{$workingDirectory}/old");
            $this->downloadStarterKitRelease->handle($release, "{$workingDirectory}/new");

            $diff = $this->diffStarterKitReleases->handle("{$workingDirectory}/old", "{$workingDirectory}/new", "{$workingDirectory}/diff.git");

            $update = new StarterKitUpdate(
                from: $manifest->release,
                to: $release,
                diff: $diff,
                removedFiles: $this->findRemovedStarterKitFiles->handle("{$workingDirectory}/old", $projectDirectory),
            );

            $commitLog = $this->fetchStarterKitCommitLog->handle($manifest->release, $release, "{$workingDirectory}/commits.git");

            $this->deleteDirectory->handle($updateDirectory);

            File::ensureDirectoryExists($updateDirectory);

            File::put("{$updateDirectory}/changes.diff", $diff->patch);

            $this->copyFiles([...$diff->modifiedFiles, ...$diff->deletedFiles], "{$workingDirectory}/old", "{$updateDirectory}/old");
            $this->copyFiles([...$diff->modifiedFiles, ...$diff->addedFiles], "{$workingDirectory}/new", "{$updateDirectory}/new");

            if ($update->removedFiles !== []) {
                File::put("{$updateDirectory}/REMOVED.md", View::make('prompts.removed', [
                    'version' => $manifest->release->version,
                    'removedFilesByDirectory' => collect($update->removedFiles)
                        ->groupBy(fn (string $path): string => dirname($path) === '.' ? 'Project root' : dirname($path))
                        ->all(),
                ])->render());
            }

            if ($commitLog !== null) {
                File::put("{$updateDirectory}/COMMITS.md", "{$commitLog}\n");
            }

            $this->recordStarterKitRelease->handle($release, $updateDirectory, $manifest->removedFeatures);

            File::put("{$updateDirectory}/PROMPT.md", View::make('prompts.update', [
                'update' => $update,
                'removedFeatures' => $manifest->removedFeatures,
                'hasCommitLog' => $commitLog !== null,
                'isManual' => ! $agent instanceof Agent,
                'updateDirectory' => $this->updateDirectoryName,
                'manifestFilename' => $this->manifestFilename,
            ])->render());

            return $update;
        } finally {
            $this->deleteDirectory->handle($workingDirectory);
        }
    }

    /**
     * @param  list<string>  $paths
     */
    private function copyFiles(array $paths, string $fromDirectory, string $toDirectory): void
    {
        foreach ($paths as $path) {
            File::ensureDirectoryExists(dirname("{$toDirectory}/{$path}"));

            File::copy("{$fromDirectory}/{$path}", "{$toDirectory}/{$path}");
        }
    }
}
