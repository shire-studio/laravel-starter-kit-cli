<?php

declare(strict_types=1);

namespace App\Data;

final readonly class TeamsAddition
{
    /**
     * @param  list<string>  $appliedFiles
     * @param  list<string>  $unresolvedFiles
     * @param  list<string>  $filesToReview
     */
    public function __construct(
        public StarterKitRelease $release,
        public array $appliedFiles,
        public array $unresolvedFiles,
        public array $filesToReview = [],
    ) {}

    public function isComplete(): bool
    {
        return $this->unresolvedFiles === [] && $this->filesToReview === [];
    }
}
