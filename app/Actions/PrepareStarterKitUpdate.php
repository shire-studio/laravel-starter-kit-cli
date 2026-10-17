<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\StarterKitManifest;
use App\Data\StarterKitRelease;
use App\Data\StarterKitUpdate;
use App\Data\UpdateDirectory;
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
    ) {}

    public function handle(StarterKitManifest $manifest, StarterKitRelease $release, UpdateDirectory $updateDirectory): StarterKitUpdate
    {
        $temporaryDirectory = sys_get_temp_dir();
        $randomName = Str::random(16);

        $workingDirectory = "{$temporaryDirectory}/laravel-starter-kit-cli/{$randomName}";

        try {
            $this->downloadStarterKitRelease->handle($manifest->release, "{$workingDirectory}/old");
            $this->downloadStarterKitRelease->handle($release, "{$workingDirectory}/new");

            $diff = $this->diffStarterKitReleases->handle("{$workingDirectory}/old", "{$workingDirectory}/new", "{$workingDirectory}/diff.git");

            $update = new StarterKitUpdate(
                from: $manifest->release,
                to: $release,
                diff: $diff,
                removedFiles: $this->findRemovedStarterKitFiles->handle("{$workingDirectory}/old", $updateDirectory->projectDirectory),
            );

            $commitLog = $this->fetchStarterKitCommitLog->handle($manifest->release, $release, "{$workingDirectory}/commits.git");

            $this->deleteDirectory->handle($updateDirectory->path());

            File::ensureDirectoryExists($updateDirectory->path());

            File::put($updateDirectory->changes(), $diff->patch);

            $this->copyFiles([...$diff->modifiedFiles, ...$diff->deletedFiles], "{$workingDirectory}/old", $updateDirectory->oldFiles());
            $this->copyFiles([...$diff->modifiedFiles, ...$diff->addedFiles], "{$workingDirectory}/new", $updateDirectory->newFiles());

            if ($update->removedFiles !== []) {
                File::put($updateDirectory->removedFiles(), View::make('prompts.removed', [
                    'version' => $manifest->release->version,
                    'removedFilesByDirectory' => collect($update->removedFiles)
                        ->groupBy(fn (string $path): string => dirname($path) === '.' ? 'Project root' : dirname($path))
                        ->all(),
                ])->render());
            }

            if ($commitLog !== null) {
                File::put($updateDirectory->commitLog(), "{$commitLog}\n");
            }

            $this->recordStarterKitRelease->handle($release, $updateDirectory->path(), $manifest->removedFeatures);

            File::put($updateDirectory->prompt(), View::make('prompts.update', [
                'update' => $update,
                'removedFeatures' => $manifest->removedFeatures,
                'hasCommitLog' => $commitLog !== null,
                'updateDirectory' => $updateDirectory,
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
