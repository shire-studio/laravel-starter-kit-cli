<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\Agent;
use Illuminate\Support\Facades\Process;

final readonly class LaunchAgent
{
    public function handle(Agent $agent, string $directory, string $prompt): int
    {
        $process = Process::path($directory)->forever();

        return $process->tty($process->supportsTty())
            ->run($agent->interactiveCommand($prompt))
            ->exitCode() ?? 1;
    }
}
