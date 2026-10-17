<?php

declare(strict_types=1);

use App\Enums\Agent;

it('builds the command that starts an interactive session with a prompt', function (Agent $agent, array $command): void {
    expect($agent->interactiveCommand('Read PROMPT.md'))->toBe($command);
})->with([
    'claude code' => [Agent::ClaudeCode, ['claude', 'Read PROMPT.md']],
    'codex' => [Agent::Codex, ['codex', 'Read PROMPT.md']],
    'gemini cli' => [Agent::Gemini, ['gemini', '--prompt-interactive', 'Read PROMPT.md']],
    'opencode' => [Agent::OpenCode, ['opencode', '--prompt', 'Read PROMPT.md']],
    'cursor' => [Agent::Cursor, ['cursor-agent', 'Read PROMPT.md']],
]);

it('has a label for every agent', function (): void {
    expect(array_map(fn (Agent $agent): string => $agent->label(), Agent::cases()))
        ->toBe(['Claude Code', 'Codex', 'Gemini CLI', 'OpenCode', 'Cursor']);
});
