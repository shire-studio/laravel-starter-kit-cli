<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\Agent;
use Illuminate\Support\Facades\Process;

final readonly class LaunchAgent
{
    public function handle(Agent $agent, string $directory, string $instructionsPath): int
    {
        $command = $agent->interactiveCommand("Read {$instructionsPath} and follow the instructions in it. The starter kit CLI started this session and carries on once you exit.");
        $terminalDevice = $this->terminalDevice();

        $process = Process::path($directory)->forever();

        if ($terminalDevice === null) {
            return $process->tty($process->supportsTty())->run($command)->exitCode() ?? 1;
        }

        return $process->run(['sh', '-c', 'exec "$@" <"$0" >"$0" 2>"$0"', $terminalDevice, ...$command])->exitCode() ?? 1;
    }

    private function terminalDevice(): ?string
    {
        $result = Process::run(['ps', '-o', 'tty=', '-p', (string) getmypid()]);

        $terminal = mb_trim($result->output());

        if ($result->failed() || in_array($terminal, ['', '?', '??'], true)) {
            return null;
        }

        return "/dev/{$terminal}";
    }
}
