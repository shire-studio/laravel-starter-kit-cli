<?php

declare(strict_types=1);

namespace App\Actions;

use Illuminate\Support\Facades\Process;

final readonly class IsGitRepository
{
    public function handle(string $directory): bool
    {
        $result = Process::path($directory)->run(['git', 'rev-parse', '--is-inside-work-tree']);

        return $result->successful() && mb_trim($result->output()) === 'true';
    }
}
