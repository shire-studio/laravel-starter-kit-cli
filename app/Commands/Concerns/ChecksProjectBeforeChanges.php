<?php

declare(strict_types=1);

namespace App\Commands\Concerns;

use App\Actions\FindMissingTools;
use App\Actions\IsGitRepository;
use App\Actions\ListUncommittedChanges;
use App\Data\UpdateDirectory;
use Illuminate\Support\Facades\File;

trait ChecksProjectBeforeChanges
{
    private function projectProblem(
        FindMissingTools $findMissingTools,
        IsGitRepository $isGitRepository,
        ListUncommittedChanges $listUncommittedChanges,
        string $directory,
        string $change,
    ): ?string {
        $missingTools = $findMissingTools->handle(['php', 'composer', 'git']);

        if ($missingTools !== []) {
            $missingToolList = implode(', ', $missingTools);

            return "The following required tools could not be found: {$missingToolList}.";
        }

        if (File::missing("{$directory}/composer.json")) {
            return "No composer.json was found in [{$directory}]. Run {$this->getName()} from the root of a project created from the starter kit.";
        }

        if (! $isGitRepository->handle($directory)) {
            return "[{$directory}] is not a git repository. Commit the project to git first so {$change} can be reviewed and undone.";
        }

        if ($this->option('force') !== true) {
            if ($listUncommittedChanges->handle($directory) !== []) {
                return "The project has uncommitted changes. Commit or stash them first so {$change} can be reviewed and undone, or use --force.";
            }
        }

        return null;
    }

    private function pendingChangesProblem(UpdateDirectory $updateDirectory): ?string
    {
        if (File::missing($updateDirectory->manifest())) {
            return null;
        }

        $stagedManifest = $updateDirectory->relative($updateDirectory->manifest());

        return "[{$updateDirectory->name}] holds starter kit changes that were not marked as done. Finish them by copying [{$stagedManifest}] to [{$updateDirectory->manifestFilename}] and deleting [{$updateDirectory->name}], or delete [{$updateDirectory->name}] to discard them. Then run {$this->getName()} again.";
    }
}
