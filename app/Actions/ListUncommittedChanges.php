<?php

declare(strict_types=1);

namespace App\Actions;

use Illuminate\Support\Facades\Process;

final readonly class ListUncommittedChanges
{
    /**
     * @return list<string>
     */
    public function handle(string $directory): array
    {
        $output = Process::path($directory)
            ->run(['git', 'status', '--short', '--untracked-files=all'])
            ->throw()
            ->output();

        return array_values(array_filter(
            explode("\n", mb_rtrim($output)),
            fn (string $line): bool => $line !== '',
        ));
    }
}
