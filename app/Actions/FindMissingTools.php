<?php

declare(strict_types=1);

namespace App\Actions;

use Illuminate\Support\Facades\Process;

final readonly class FindMissingTools
{
    /**
     * @param  list<string>  $tools
     * @return list<string>
     */
    public function handle(array $tools): array
    {
        return array_values(array_filter(
            $tools,
            fn (string $tool): bool => Process::run([$tool, '--version'])->failed(),
        ));
    }
}
