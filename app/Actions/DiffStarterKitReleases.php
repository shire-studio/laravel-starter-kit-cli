<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\StarterKitDiff;
use Illuminate\Container\Attributes\Config;
use Illuminate\Support\Facades\Process;

final readonly class DiffStarterKitReleases
{
    private const array LOCK_FILES = ['composer.lock', 'pnpm-lock.yaml'];

    public function __construct(
        #[Config('starter-kit.manifest_filename')]
        private string $manifestFilename,
    ) {}

    public function handle(string $oldDirectory, string $newDirectory, string $repositoryDirectory): StarterKitDiff
    {
        Process::run(['git', 'init', '--quiet', '--bare', $repositoryDirectory])->throw();

        $oldTree = $this->writeTree($repositoryDirectory, $oldDirectory);
        $newTree = $this->writeTree($repositoryDirectory, $newDirectory);

        $excludedPaths = ['--', ...array_map(
            fn (string $path): string => ":(exclude){$path}",
            [...self::LOCK_FILES, $this->manifestFilename],
        )];

        $patch = $this->git($repositoryDirectory, [
            'diff', '--no-color', '--no-ext-diff', '--no-renames', '--src-prefix=a/', '--dst-prefix=b/', $oldTree, $newTree, ...$excludedPaths,
        ]);

        $changes = $this->changes($this->git($repositoryDirectory, [
            'diff', '--name-status', '-z', '--no-renames', $oldTree, $newTree, ...$excludedPaths,
        ]));

        return new StarterKitDiff(
            patch: $patch,
            addedFiles: $changes['A'] ?? [],
            modifiedFiles: $changes['M'] ?? [],
            deletedFiles: $changes['D'] ?? [],
        );
    }

    private function writeTree(string $repositoryDirectory, string $workTree): string
    {
        $this->git($repositoryDirectory, ["--work-tree={$workTree}", 'add', '--all', '--force']);

        return mb_trim($this->git($repositoryDirectory, ['write-tree']));
    }

    /**
     * @return array<string, list<string>>
     */
    private function changes(string $nameStatus): array
    {
        if ($nameStatus === '') {
            return [];
        }

        $fields = explode("\0", mb_rtrim($nameStatus, "\n\0"));

        $changes = [];

        foreach (array_chunk($fields, 2) as [$status, $path]) {
            $changeType = in_array($status, ['A', 'D'], true)
                ? $status
                : 'M';

            $changes[$changeType][] = $path;
        }

        return $changes;
    }

    /**
     * @param  list<string>  $arguments
     */
    private function git(string $repositoryDirectory, array $arguments): string
    {
        return Process::path($repositoryDirectory)
            ->run(['git', "--git-dir={$repositoryDirectory}", ...$arguments])
            ->throw()
            ->output();
    }
}
