<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\StarterKitDiff;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

final readonly class ApplyTeamsChanges
{
    /**
     * @return list<string>
     */
    public function handle(StarterKitDiff $diff, string $baseDirectory, string $teamsDirectory, string $projectDirectory): array
    {
        $unresolvedFiles = [];

        foreach ([...$diff->addedFiles, ...$diff->modifiedFiles, ...$diff->deletedFiles] as $path) {
            $applied = ! $this->isBlocked($projectDirectory, $path)
                && $this->apply("{$baseDirectory}/{$path}", "{$teamsDirectory}/{$path}", "{$projectDirectory}/{$path}");

            if (! $applied) {
                $unresolvedFiles[] = $path;
            }
        }

        sort($unresolvedFiles);

        return $unresolvedFiles;
    }

    private function isBlocked(string $projectDirectory, string $path): bool
    {
        $projectFile = "{$projectDirectory}/{$path}";

        if (File::exists($projectFile) && ! File::isFile($projectFile)) {
            return true;
        }

        for ($parent = dirname($path); $parent !== '.'; $parent = dirname($parent)) {
            if (File::isFile("{$projectDirectory}/{$parent}")) {
                return true;
            }
        }

        return false;
    }

    private function apply(string $baseFile, string $teamsFile, string $projectFile): bool
    {
        if (File::missing($teamsFile)) {
            return $this->delete($baseFile, $projectFile);
        }

        if (File::missing($projectFile)) {
            return File::missing($baseFile) && $this->copy($teamsFile, $projectFile);
        }

        if ($this->hasSameContents($projectFile, $teamsFile)) {
            $this->applyExecutableBit($baseFile, $teamsFile, $projectFile);

            return true;
        }

        if (File::missing($baseFile)) {
            return false;
        }

        if ($this->hasSameContents($projectFile, $baseFile)) {
            return $this->copy($teamsFile, $projectFile);
        }

        return $this->merge($baseFile, $teamsFile, $projectFile);
    }

    private function delete(string $baseFile, string $projectFile): bool
    {
        if (File::missing($projectFile)) {
            return true;
        }

        if (! $this->hasSameContents($projectFile, $baseFile)) {
            return false;
        }

        return File::delete($projectFile);
    }

    private function copy(string $teamsFile, string $projectFile): bool
    {
        File::ensureDirectoryExists(dirname($projectFile));

        File::copy($teamsFile, $projectFile);

        File::chmod($projectFile, fileperms($teamsFile) & 0o777);

        return true;
    }

    private function merge(string $baseFile, string $teamsFile, string $projectFile): bool
    {
        $result = Process::run(['git', 'merge-file', '--stdout', $projectFile, $baseFile, $teamsFile]);

        if ($result->failed()) {
            return false;
        }

        File::put($projectFile, $result->output());

        $this->applyExecutableBit($baseFile, $teamsFile, $projectFile);

        return true;
    }

    private function applyExecutableBit(string $baseFile, string $teamsFile, string $projectFile): void
    {
        $isExecutable = is_executable($teamsFile);

        if (File::exists($baseFile) && is_executable($baseFile) === $isExecutable) {
            return;
        }

        $permissions = fileperms($projectFile) & 0o777;

        File::chmod($projectFile, $isExecutable ? $permissions | 0o111 : $permissions & ~0o111);
    }

    private function hasSameContents(string $file, string $otherFile): bool
    {
        return File::get($file) === File::get($otherFile);
    }
}
