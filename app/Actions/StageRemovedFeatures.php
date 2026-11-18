<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\StarterKitManifest;
use App\Data\UpdateDirectory;
use Illuminate\Support\Facades\File;

final readonly class StageRemovedFeatures
{
    public function __construct(
        private RecordStarterKitRelease $recordStarterKitRelease,
    ) {}

    public function handle(UpdateDirectory $updateDirectory, StarterKitManifest $manifest): void
    {
        $this->recordStarterKitRelease->handle($manifest, $updateDirectory->path());

        File::delete($updateDirectory->removedFeatures());
    }
}
