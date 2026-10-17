<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\StarterKitRelease;
use App\Data\UpdateDirectory;
use Illuminate\Support\Facades\File;

final readonly class StageRemovedFeatures
{
    public function __construct(
        private RecordStarterKitRelease $recordStarterKitRelease,
    ) {}

    /**
     * @param  list<string>  $removedFeatures
     */
    public function handle(UpdateDirectory $updateDirectory, StarterKitRelease $release, array $removedFeatures): void
    {
        $this->recordStarterKitRelease->handle($release, $updateDirectory->path(), $removedFeatures);

        File::delete($updateDirectory->removedFeatures());
    }
}
