<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\UpdateDirectory;
use Illuminate\Support\Facades\File;

final readonly class ReadDetectedStarterKitVersion
{
    public function handle(UpdateDirectory $updateDirectory): ?string
    {
        if (File::missing($updateDirectory->detectedVersion())) {
            return null;
        }

        return mb_trim(File::get($updateDirectory->detectedVersion()));
    }
}
