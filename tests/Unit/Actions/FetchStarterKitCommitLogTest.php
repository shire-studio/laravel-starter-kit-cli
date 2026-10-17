<?php

declare(strict_types=1);

use App\Actions\FetchStarterKitCommitLog;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;

it('fetches the commit messages between two releases', function (): void {
    Process::fake([
        "*'clone'*" => Process::result(),
        "*'log'*" => Process::result("## feat: add two factor authentication\n\nAdds TOTP support.\n\n"),
    ]);

    $commitLog = resolve(FetchStarterKitCommitLog::class)->handle(starterKitRelease('v1.3.0', 'a1b2c3'), starterKitRelease(), '/tmp/commits.git');

    expect($commitLog)->toBe("## feat: add two factor authentication\n\nAdds TOTP support.");

    Process::assertRanInOrder([
        fn (PendingProcess $process): bool => $process->command === ['git', 'clone', '--quiet', '--bare', '--filter=tree:0', 'https://github.com/shire-studio/laravel-starter-kit.git', '/tmp/commits.git']
            && $process->environment === ['GIT_TERMINAL_PROMPT' => '0'],
        ['git', '--git-dir=/tmp/commits.git', 'log', '--no-merges', '--format=## %s%n%n%b%n', 'v1.3.0..v1.4.0'],
    ]);
});

it('returns nothing when the repository cannot be cloned', function (): void {
    Process::fake([
        "*'clone'*" => Process::result(errorOutput: 'fatal: could not read Username', exitCode: 128),
    ]);

    expect(resolve(FetchStarterKitCommitLog::class)->handle(starterKitRelease('v1.3.0', 'a1b2c3'), starterKitRelease(), '/tmp/commits.git'))->toBeNull();

    Process::assertRanTimes(fn (PendingProcess $process): bool => true, 1);
});

it('returns nothing when the log cannot be read', function (): void {
    Process::fake([
        "*'clone'*" => Process::result(),
        "*'log'*" => Process::result(errorOutput: 'fatal: bad revision', exitCode: 128),
    ]);

    expect(resolve(FetchStarterKitCommitLog::class)->handle(starterKitRelease('v1.3.0', 'a1b2c3'), starterKitRelease(), '/tmp/commits.git'))->toBeNull();
});

it('returns nothing when there are no commits between the releases', function (): void {
    Process::fake();

    expect(resolve(FetchStarterKitCommitLog::class)->handle(starterKitRelease('v1.3.0', 'a1b2c3'), starterKitRelease(), '/tmp/commits.git'))->toBeNull();
});
