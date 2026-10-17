<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\StarterKitRelease;
use App\Data\UpdateDirectory;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;

final readonly class PrepareStarterKitDetection
{
    public function __construct(
        private DeleteDirectory $deleteDirectory,
    ) {}

    /**
     * @param  list<string>  $versions
     */
    public function handle(UpdateDirectory $updateDirectory, StarterKitRelease $latestRelease, array $versions): void
    {
        $this->deleteDirectory->handle($updateDirectory->path());

        File::ensureDirectoryExists($updateDirectory->path());

        File::put($updateDirectory->detectionPrompt(), View::make('prompts.detect', [
            'release' => $latestRelease,
            'versions' => $versions,
            'updateDirectory' => $updateDirectory,
        ])->render());
    }
}
