<?php

declare(strict_types=1);

use App\Actions\IgnoreUpdateDirectory;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

beforeEach(function (): void {
    $this->directory = temporaryDirectory();
    $this->excludePath = "{$this->directory}/.git/info/exclude";

    Process::fake([
        '*' => Process::result("{$this->excludePath}\n"),
    ]);
});

afterEach(function (): void {
    File::deleteDirectory($this->directory);
});

it('excludes the update directory from git', function (): void {
    createFiles($this->directory, ['.git/info/exclude' => "# git ls-files --others --exclude-from=.git/info/exclude\n"]);

    resolve(IgnoreUpdateDirectory::class)->handle($this->directory);

    expect(File::get($this->excludePath))->toBe("# git ls-files --others --exclude-from=.git/info/exclude\n/.starter-kit-update/\n");

    Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['git', 'rev-parse', '--path-format=absolute', '--git-path', 'info/exclude'] && $process->path === $this->directory);
});

it('creates the exclude file when it does not exist', function (): void {
    resolve(IgnoreUpdateDirectory::class)->handle($this->directory);

    expect(File::get($this->excludePath))->toBe("/.starter-kit-update/\n");
});

it('starts a new line when the exclude file does not end with one', function (): void {
    createFiles($this->directory, ['.git/info/exclude' => '*.log']);

    resolve(IgnoreUpdateDirectory::class)->handle($this->directory);

    expect(File::get($this->excludePath))->toBe("*.log\n/.starter-kit-update/\n");
});

it('does not exclude the update directory twice', function (): void {
    createFiles($this->directory, ['.git/info/exclude' => "/.starter-kit-update/\n"]);

    resolve(IgnoreUpdateDirectory::class)->handle($this->directory);

    expect(File::get($this->excludePath))->toBe("/.starter-kit-update/\n");
});

it('excludes the configured update directory', function (): void {
    config(['starter-kit.update_directory' => '.upstream']);

    resolve(IgnoreUpdateDirectory::class)->handle($this->directory);

    expect(File::get($this->excludePath))->toBe("/.upstream/\n");
});
