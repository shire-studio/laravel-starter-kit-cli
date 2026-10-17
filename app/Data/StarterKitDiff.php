<?php

declare(strict_types=1);

namespace App\Data;

final readonly class StarterKitDiff
{
    /**
     * @param  list<string>  $addedFiles
     * @param  list<string>  $modifiedFiles
     * @param  list<string>  $deletedFiles
     */
    public function __construct(
        public string $patch,
        public array $addedFiles,
        public array $modifiedFiles,
        public array $deletedFiles,
    ) {}

    public function hasChanges(): bool
    {
        return $this->changedFileCount() > 0;
    }

    public function changedFileCount(): int
    {
        return count($this->addedFiles) + count($this->modifiedFiles) + count($this->deletedFiles);
    }
}
