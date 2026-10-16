<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\StarterKitRelease;
use Illuminate\Support\Facades\Process;

final readonly class FetchStarterKitCommitLog
{
    public function handle(StarterKitRelease $from, StarterKitRelease $to, string $repositoryDirectory): ?string
    {
        $clone = Process::env(['GIT_TERMINAL_PROMPT' => '0'])
            ->run(['git', 'clone', '--quiet', '--bare', '--filter=tree:0', $to->repository, $repositoryDirectory]);

        if ($clone->failed()) {
            return null;
        }

        $log = Process::run([
            'git',
            "--git-dir={$repositoryDirectory}",
            'log',
            '--no-merges',
            '--format=## %s%n%n%b%n',
            "{$from->reference}..{$to->reference}",
        ]);

        if ($log->failed()) {
            return null;
        }

        $commitLog = mb_trim($log->output());

        return $commitLog === '' ? null : $commitLog;
    }
}
