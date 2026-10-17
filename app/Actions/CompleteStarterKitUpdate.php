<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\UpdateDirectory;
use App\Exceptions\CouldNotCompleteStarterKitUpdate;
use Illuminate\Support\Facades\File;

final readonly class CompleteStarterKitUpdate
{
    public function __construct(
        private DeleteDirectory $deleteDirectory,
    ) {}

    public function handle(UpdateDirectory $updateDirectory): void
    {
        if (File::missing($updateDirectory->manifest())) {
            throw CouldNotCompleteStarterKitUpdate::missingManifest($updateDirectory->relative($updateDirectory->manifest()));
        }

        File::copy($updateDirectory->manifest(), "{$updateDirectory->projectDirectory}/{$updateDirectory->manifestFilename}");

        $this->deleteDirectory->handle($updateDirectory->path());
    }
}
