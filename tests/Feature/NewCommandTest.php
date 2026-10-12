<?php

declare(strict_types=1);

use Illuminate\Process\FakeProcessResult;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

beforeEach(function (): void {
    $this->originalWorkingDirectory = getcwd();
    $this->workingDirectory = temporaryDirectory();
    $this->projectDirectory = "{$this->workingDirectory}/example-app";

    chdir($this->workingDirectory);
});

afterEach(function (): void {
    chdir($this->originalWorkingDirectory);

    File::deleteDirectory($this->workingDirectory);
});

function fakeStarterKitProcesses(array $overrides = []): void
{
    Process::fake($overrides + [
        "*'show'*'v1.4.0'*" => Process::result(composerShowOutput(['v1.4.0'])),
        "*'show'*" => Process::result(composerShowOutput(['v1.4.0', 'v1.3.10'])),
        "*'create-project'*" => function (PendingProcess $process): FakeProcessResult {
            File::ensureDirectoryExists($process->command[3]);

            return Process::result();
        },
        '*' => Process::result(),
    ]);
}

it('creates a project from the latest starter kit release', function (): void {
    fakeStarterKitProcesses();

    $this->artisan('new', ['name' => 'example-app', '--pnpm' => true, '--git' => true, '--no-interaction' => true])
        ->expectsOutputToContain('Using starter kit v1.4.0.')
        ->expectsOutputToContain("Project created in [{$this->projectDirectory}].")
        ->expectsOutputToContain('Frontend assets built.')
        ->expectsOutputToContain('Git repository initialised.')
        ->expectsOutputToContain('Your project is ready.')
        ->doesntExpectOutputToContain('pnpm install')
        ->assertSuccessful();

    Process::assertRan(['composer', 'create-project', 'shire-studio/laravel-starter-kit', $this->projectDirectory, 'v1.4.0', '--prefer-dist', '--no-interaction']);
    Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['pnpm', 'run', 'build'] && $process->path === $this->projectDirectory);
    Process::assertRan(['git', 'commit', '--quiet', '--message=chore: create project from starter kit v1.4.0']);

    expect(File::json("{$this->projectDirectory}/.starter-kit.json"))->toBe([
        'package' => 'shire-studio/laravel-starter-kit',
        'version' => 'v1.4.0',
        'reference' => 'c31ae68197d092fbaac6ac68712b44f230e27850',
        'repository' => 'https://github.com/shire-studio/laravel-starter-kit.git',
    ]);
});

it('asks for the project name and optional steps', function (): void {
    fakeStarterKitProcesses();

    $this->artisan('new')
        ->expectsQuestion('What is the name of your project?', 'example-app')
        ->expectsConfirmation('Would you like to install the frontend dependencies and build the assets?', 'no')
        ->expectsConfirmation('Would you like to initialise a git repository?', 'no')
        ->expectsOutputToContain('pnpm install')
        ->assertSuccessful();

    Process::assertRan(['composer', 'create-project', 'shire-studio/laravel-starter-kit', $this->projectDirectory, 'v1.4.0', '--prefer-dist', '--no-interaction']);
    Process::assertDidntRun(['pnpm', 'install']);
    Process::assertDidntRun(['git', 'add', '--all']);
});

it('skips the optional steps when running without interaction', function (): void {
    fakeStarterKitProcesses();

    $this->artisan('new', ['name' => 'example-app', '--no-interaction' => true])
        ->assertSuccessful();

    Process::assertDidntRun(['pnpm', '--version']);
    Process::assertDidntRun(['git', '--version']);
    Process::assertDidntRun(['pnpm', 'install']);
    Process::assertDidntRun(['git', 'add', '--all']);
});

it('rejects a project name that starts with a dot', function (): void {
    Process::fake();

    $this->artisan('new', ['name' => '.example-app', '--no-interaction' => true])
        ->expectsOutputToContain('The name may only contain letters, numbers, dashes, underscores and dots')
        ->assertFailed();

    Process::assertNothingRan();
});

it('rejects a project name that points to the parent directory', function (): void {
    Process::fake();

    $this->artisan('new', ['name' => '..', '--no-interaction' => true])
        ->expectsOutputToContain('The name may only contain letters, numbers, dashes, underscores and dots')
        ->assertFailed();

    Process::assertNothingRan();
});

it('rejects a project name that contains a path separator', function (): void {
    Process::fake();

    $this->artisan('new', ['name' => 'projects/example-app', '--no-interaction' => true])
        ->expectsOutputToContain('The name may only contain letters, numbers, dashes, underscores and dots')
        ->assertFailed();

    Process::assertNothingRan();
});

it('rejects a project name that contains spaces', function (): void {
    Process::fake();

    $this->artisan('new', ['name' => 'example app', '--no-interaction' => true])
        ->expectsOutputToContain('The name may only contain letters, numbers, dashes, underscores and dots')
        ->assertFailed();

    Process::assertNothingRan();
});

it('refuses to overwrite an existing directory', function (): void {
    Process::fake();

    File::ensureDirectoryExists($this->projectDirectory);

    $this->artisan('new', ['name' => 'example-app', '--no-interaction' => true])
        ->expectsOutputToContain('The directory [example-app] already exists. Use --force to overwrite it.')
        ->assertFailed();

    Process::assertNothingRan();
});

it('overwrites an existing directory when forced', function (): void {
    fakeStarterKitProcesses();

    File::ensureDirectoryExists($this->projectDirectory);
    File::put("{$this->projectDirectory}/old-file.txt", 'old');

    $this->artisan('new', ['name' => 'example-app', '--force' => true, '--no-interaction' => true])
        ->expectsOutputToContain('Removing the existing [example-app] directory.')
        ->assertSuccessful();

    expect("{$this->projectDirectory}/old-file.txt")->not->toBeFile()
        ->and("{$this->projectDirectory}/.starter-kit.json")->toBeFile();
});

it('keeps an existing directory when the starter kit release cannot be resolved', function (): void {
    fakeStarterKitProcesses([
        "*'show'*" => Process::result(errorOutput: 'Could not reach packagist.org.', exitCode: 1),
    ]);

    File::ensureDirectoryExists($this->projectDirectory);
    File::put("{$this->projectDirectory}/old-file.txt", 'old');

    $this->artisan('new', ['name' => 'example-app', '--force' => true, '--no-interaction' => true])
        ->expectsOutputToContain('Could not reach packagist.org.')
        ->assertFailed();

    expect("{$this->projectDirectory}/old-file.txt")->toBeFile();
});

it('fails when required tools are missing', function (): void {
    fakeStarterKitProcesses([
        "'pnpm' '--version'" => Process::result(exitCode: 127),
        "'git' '--version'" => Process::result(exitCode: 127),
    ]);

    File::ensureDirectoryExists($this->projectDirectory);

    $this->artisan('new', ['name' => 'example-app', '--pnpm' => true, '--git' => true, '--force' => true, '--no-interaction' => true])
        ->expectsOutputToContain('The following required tools could not be found: pnpm, git.')
        ->assertFailed();

    expect($this->projectDirectory)->toBeDirectory();

    Process::assertDidntRun(fn (PendingProcess $process): bool => in_array('create-project', (array) $process->command, true));
});

it('fails when a subprocess fails', function (): void {
    fakeStarterKitProcesses([
        "'pnpm' 'run' 'build'" => Process::result(output: 'Build output', errorOutput: 'Build failed', exitCode: 1),
    ]);

    $this->artisan('new', ['name' => 'example-app', '--pnpm' => true, '--git' => true, '--no-interaction' => true])
        ->expectsOutputToContain('Build failed')
        ->expectsOutputToContain('Build output')
        ->expectsOutputToContain("The command ['pnpm' 'run' 'build'] failed with exit code 1.")
        ->assertFailed();

    Process::assertDidntRun(['git', 'add', '--all']);
});

it('fails when no stable starter kit release exists', function (): void {
    fakeStarterKitProcesses([
        "*'show'*" => Process::result(composerShowOutput(['dev-main'])),
    ]);

    $this->artisan('new', ['name' => 'example-app', '--no-interaction' => true])
        ->expectsOutputToContain('No stable release of [shire-studio/laravel-starter-kit] could be found.')
        ->assertFailed();

    Process::assertDidntRun(fn (PendingProcess $process): bool => in_array('create-project', (array) $process->command, true));
});
