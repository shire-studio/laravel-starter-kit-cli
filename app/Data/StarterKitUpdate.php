<?php

declare(strict_types=1);

namespace App\Data;

final readonly class StarterKitUpdate
{
    /**
     * @param  list<string>  $removedFiles
     */
    public function __construct(
        public StarterKitRelease $from,
        public StarterKitRelease $to,
        public StarterKitDiff $diff,
        public array $removedFiles,
    ) {}
}
