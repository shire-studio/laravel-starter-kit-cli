<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\UpdateDirectory;
use Illuminate\Support\Facades\File;

final readonly class CompleteStarterKitUpdate
{
    public function __construct(
        private DeleteDirectory $deleteDirectory,
    ) {}

    public function handle(UpdateDirectory $updateDirectory): void
    {
        File::copy($updateDirectory->manifest(), "{$updateDirectory->projectDirectory}/{$updateDirectory->manifestFilename}");

        $this->deleteDirectory->handle($updateDirectory->path());
    }
}
