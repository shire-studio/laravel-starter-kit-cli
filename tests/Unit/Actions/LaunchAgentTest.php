<?php

declare(strict_types=1);

use App\Actions\LaunchAgent;
use App\Enums\Agent;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;

it('connects the agent session to the real terminal device', function (): void {
    Process::fake([
        "'ps'*" => Process::result("ttys002\n"),
        '*' => Process::result(),
    ]);

    $exitCode = resolve(LaunchAgent::class)->handle(Agent::OpenCode, '/projects/example-app', 'Read PROMPT.md');

    expect($exitCode)->toBe(0);

    Process::assertRan(['ps', '-o', 'tty=', '-p', (string) getmypid()]);

    Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['sh', '-c', 'exec "$@" <"$0" >"$0" 2>"$0"', '/dev/ttys002', 'opencode', '--prompt', 'Read PROMPT.md']
        && $process->path === '/projects/example-app'
        && $process->timeout === null
        && $process->tty === false);
});

it('falls back to tty mode when there is no terminal device', function (): void {
    Process::fake([
        "'ps'*" => Process::result("??\n"),
        '*' => Process::result(),
    ]);

    resolve(LaunchAgent::class)->handle(Agent::ClaudeCode, '/projects/example-app', 'Read PROMPT.md');

    Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['claude', 'Read PROMPT.md']
        && $process->path === '/projects/example-app'
        && $process->timeout === null);
});

it('falls back to tty mode when the terminal device cannot be found', function (): void {
    Process::fake([
        "'ps'*" => Process::result(exitCode: 1),
        '*' => Process::result(),
    ]);

    resolve(LaunchAgent::class)->handle(Agent::ClaudeCode, '/projects/example-app', 'Read PROMPT.md');

    Process::assertRan(['claude', 'Read PROMPT.md']);
});

it('returns the exit code of the agent', function (): void {
    Process::fake([
        "'ps'*" => Process::result("ttys002\n"),
        '*' => Process::result(exitCode: 130),
    ]);

    expect(resolve(LaunchAgent::class)->handle(Agent::ClaudeCode, '/projects/example-app', 'Read PROMPT.md'))->toBe(130);
});
