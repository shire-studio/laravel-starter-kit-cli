<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\StarterKitManifest;
use App\Data\TeamsAddition;
use App\Data\UpdateDirectory;
use App\Exceptions\CouldNotAddTeams;
use ErrorException;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

final readonly class PrepareTeamsAddition
{
    public function __construct(
        private DownloadStarterKitRelease $downloadStarterKitRelease,
        private ApplyStarterKitVariant $applyStarterKitVariant,
        private DiffStarterKitReleases $diffStarterKitReleases,
        private ApplyTeamsChanges $applyTeamsChanges,
        private DeleteDirectory $deleteDirectory,
        private RecordStarterKitRelease $recordStarterKitRelease,
    ) {}

    public function handle(StarterKitManifest $manifest, UpdateDirectory $updateDirectory): TeamsAddition
    {
        $temporaryDirectory = sys_get_temp_dir();
        $randomName = Str::random(16);

        $workingDirectory = "{$temporaryDirectory}/laravel-starter-kit-cli/{$randomName}";

        try {
            $this->downloadStarterKitRelease->handle($manifest->release, "{$workingDirectory}/base");
            $this->applyStarterKitVariant->handle($manifest->release, "{$workingDirectory}/base", false);

            $this->downloadStarterKitRelease->handle($manifest->release, "{$workingDirectory}/teams");
            $this->applyStarterKitVariant->handle($manifest->release, "{$workingDirectory}/teams", true);

            $diff = $this->diffStarterKitReleases->handle("{$workingDirectory}/base", "{$workingDirectory}/teams", "{$workingDirectory}/diff.git");

            try {
                $unresolvedFiles = $this->applyTeamsChanges->handle($diff, "{$workingDirectory}/base", "{$workingDirectory}/teams", $updateDirectory->projectDirectory);
            } catch (ErrorException|FileNotFoundException $exception) {
                throw CouldNotAddTeams::interrupted($exception);
            }

            $changedFiles = [...$diff->addedFiles, ...$diff->modifiedFiles, ...$diff->deletedFiles];

            sort($changedFiles);

            $addedFiles = array_values(array_diff($diff->addedFiles, $unresolvedFiles));

            sort($addedFiles);

            $addition = new TeamsAddition(
                release: $manifest->release,
                appliedFiles: array_values(array_diff($changedFiles, $unresolvedFiles)),
                unresolvedFiles: $unresolvedFiles,
                filesToReview: $manifest->removedFeatures === [] ? [] : $addedFiles,
            );

            $this->deleteDirectory->handle($updateDirectory->path());

            File::ensureDirectoryExists($updateDirectory->path());

            $this->recordStarterKitRelease->handle(new StarterKitManifest($manifest->release, $manifest->removedFeatures, teams: true), $updateDirectory->path());

            if (! $addition->isComplete()) {
                File::put($updateDirectory->changes(), $diff->patch);

                $this->copyFiles($unresolvedFiles, "{$workingDirectory}/base", $updateDirectory->oldFiles());
                $this->copyFiles($unresolvedFiles, "{$workingDirectory}/teams", $updateDirectory->newFiles());

                File::put($updateDirectory->prompt(), View::make('prompts.teams', [
                    'addition' => $addition,
                    'removedFeatures' => $manifest->removedFeatures,
                    'updateDirectory' => $updateDirectory,
                ])->render());
            }

            return $addition;
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
            if (File::missing("{$fromDirectory}/{$path}")) {
                continue;
            }

            File::ensureDirectoryExists(dirname("{$toDirectory}/{$path}"));

            File::copy("{$fromDirectory}/{$path}", "{$toDirectory}/{$path}");
        }
    }
}
