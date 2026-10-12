<?php

declare(strict_types=1);

use App\Actions\FindMissingTools;
use Illuminate\Support\Facades\Process;

it('returns the tools that cannot be run', function (): void {
    Process::fake([
        "'pnpm' '--version'" => Process::result(exitCode: 127),
        "'git' '--version'" => Process::result(exitCode: 127),
        '*' => Process::result('1.0.0'),
    ]);

    $missingTools = resolve(FindMissingTools::class)->handle(['php', 'composer', 'pnpm', 'git']);

    expect($missingTools)->toBe(['pnpm', 'git']);
});

it('returns nothing when every tool is installed', function (): void {
    Process::fake();

    $missingTools = resolve(FindMissingTools::class)->handle(['php', 'composer']);

    expect($missingTools)->toBeEmpty();

    Process::assertRanInOrder([
        ['php', '--version'],
        ['composer', '--version'],
    ]);
});
