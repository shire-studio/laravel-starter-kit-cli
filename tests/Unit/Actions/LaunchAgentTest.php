<?php

declare(strict_types=1);

use App\Actions\LaunchAgent;
use App\Enums\Agent;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;

it('starts an interactive agent session in the project', function (): void {
    Process::fake();

    $exitCode = resolve(LaunchAgent::class)->handle(Agent::OpenCode, '/projects/example-app', 'Read PROMPT.md');

    expect($exitCode)->toBe(0);

    Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['opencode', '--prompt', 'Read PROMPT.md']
        && $process->path === '/projects/example-app'
        && $process->timeout === null);
});

it('returns the exit code of the agent', function (): void {
    Process::fake([
        '*' => Process::result(exitCode: 130),
    ]);

    expect(resolve(LaunchAgent::class)->handle(Agent::ClaudeCode, '/projects/example-app', 'Read PROMPT.md'))->toBe(130);
});
