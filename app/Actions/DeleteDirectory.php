<?php

declare(strict_types=1);

namespace App\Actions;

use Illuminate\Support\Facades\File;

final readonly class DeleteDirectory
{
    public function handle(string $directory): void
    {
        File::deleteDirectory($directory);
    }
}
