<?php

declare(strict_types=1);

namespace App\Data;

final readonly class StarterKitRelease
{
    public function __construct(
        public string $package,
        public string $version,
        public ?string $reference,
        public string $repository,
    ) {}
}
