<?php

declare(strict_types=1);

use App\Actions\ListUncommittedChanges;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;

it('lists the uncommitted changes', function (): void {
    Process::fake([
        '*' => Process::result(" M routes/web.php\n?? app/Actions/NewAction.php\n"),
    ]);

    $uncommittedChanges = resolve(ListUncommittedChanges::class)->handle('/projects/example-app');

    expect($uncommittedChanges)->toBe([' M routes/web.php', '?? app/Actions/NewAction.php']);

    Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['git', 'status', '--short', '--untracked-files=all', '--', '.'] && $process->path === '/projects/example-app');
});

it('returns nothing when the working tree is clean', function (): void {
    Process::fake();

    expect(resolve(ListUncommittedChanges::class)->handle('/projects/example-app'))->toBeEmpty();
});
