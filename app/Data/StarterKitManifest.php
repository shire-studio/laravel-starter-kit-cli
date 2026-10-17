<?php

declare(strict_types=1);

namespace App\Data;

final readonly class StarterKitManifest
{
    /**
     * @param  list<string>  $removedFeatures
     */
    public function __construct(
        public StarterKitRelease $release,
        public array $removedFeatures = [],
    ) {}
}
