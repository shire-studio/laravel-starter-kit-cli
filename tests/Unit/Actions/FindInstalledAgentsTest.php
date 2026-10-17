<?php

declare(strict_types=1);

use App\Actions\FindInstalledAgents;
use App\Enums\Agent;
use Illuminate\Support\Facades\Process;

it('returns the agents that can be run', function (): void {
    Process::fake([
        "'codex' '--version'" => Process::result(exitCode: 127),
        "'cursor-agent' '--version'" => Process::result(exitCode: 127),
        '*' => Process::result('1.0.0'),
    ]);

    $installedAgents = resolve(FindInstalledAgents::class)->handle();

    expect($installedAgents)->toBe([Agent::ClaudeCode, Agent::Gemini, Agent::OpenCode]);

    Process::assertRanInOrder([
        ['claude', '--version'],
        ['codex', '--version'],
        ['gemini', '--version'],
        ['opencode', '--version'],
        ['cursor-agent', '--version'],
    ]);
});
