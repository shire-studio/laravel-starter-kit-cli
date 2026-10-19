<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\Kit;

final readonly class StarterKitRelease
{
    public function __construct(
        public Kit $kit,
        public string $version,
        public ?string $reference,
        public string $repository,
    ) {}
}
