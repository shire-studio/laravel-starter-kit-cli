<?php

declare(strict_types=1);

namespace App\Actions;

use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\SplFileInfo;

final readonly class FindRemovedStarterKitFiles
{
    /**
     * @return list<string>
     */
    public function handle(string $starterKitDirectory, string $projectDirectory): array
    {
        $starterKitFiles = array_map(
            fn (SplFileInfo $file): string => $file->getRelativePathname(),
            File::allFiles($starterKitDirectory, hidden: true),
        );

        $removedFiles = array_values(array_filter(
            $starterKitFiles,
            fn (string $path): bool => File::missing("{$projectDirectory}/{$path}"),
        ));

        sort($removedFiles);

        return $removedFiles;
    }
}
