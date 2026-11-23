<?php

declare(strict_types=1);

use App\Actions\InstallProject;
use Illuminate\Process\Exceptions\ProcessFailedException;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

beforeEach(function (): void {
    $this->directory = temporaryDirectory();
});

afterEach(function (): void {
    File::deleteDirectory($this->directory);
});

it('installs the project between the create-project scripts', function (): void {
    Process::fake();

    createFiles($this->directory, ['composer.json' => (string) json_encode(['scripts' => [
        'post-create-project-cmd' => ['@php artisan key:generate --ansi'],
        'post-autoload-dump' => ['@php artisan package:discover --ansi'],
        'post-root-package-install' => ['@php -r "file_exists(\'.env\') || copy(\'.env.example\', \'.env\');"'],
        'test' => ['pest'],
    ]])]);

    resolve(InstallProject::class)->handle(starterKitRelease(), $this->directory);

    Process::assertRanInOrder([
        fn (PendingProcess $process): bool => $process->command === ['composer', 'run-script', 'post-root-package-install', '--no-interaction'] && $process->path === $this->directory,
        fn (PendingProcess $process): bool => $process->command === ['composer', 'install', '--prefer-dist', '--no-interaction']
            && $process->path === $this->directory
            && $process->environment === ['COMPOSER_ROOT_VERSION' => 'v1.4.0'],
        ['composer', 'run-script', 'post-create-project-cmd', '--no-interaction'],
    ]);
});

it('only runs the create-project scripts the project defines', function (): void {
    Process::fake();

    createFiles($this->directory, ['composer.json' => (string) json_encode(['scripts' => [
        'post-create-project-cmd' => ['@php artisan key:generate --ansi'],
    ]])]);

    resolve(InstallProject::class)->handle(starterKitRelease(), $this->directory);

    Process::assertRanInOrder([
        ['composer', 'install', '--prefer-dist', '--no-interaction'],
        ['composer', 'run-script', 'post-create-project-cmd', '--no-interaction'],
    ]);
});

it('installs a project without scripts', function (): void {
    Process::fake();

    createFiles($this->directory, ['composer.json' => '{"name": "acme/example"}']);

    resolve(InstallProject::class)->handle(starterKitRelease(), $this->directory);

    Process::assertRanInOrder([
        ['composer', 'install', '--prefer-dist', '--no-interaction'],
    ]);
});

it('stops when the install fails', function (): void {
    Process::fake([
        "*'install'*" => Process::result(exitCode: 1),
        '*' => Process::result(),
    ]);

    createFiles($this->directory, ['composer.json' => (string) json_encode(['scripts' => [
        'post-create-project-cmd' => ['@php artisan key:generate --ansi'],
    ]])]);

    expect(fn () => resolve(InstallProject::class)->handle(starterKitRelease(), $this->directory))->toThrow(ProcessFailedException::class);

    Process::assertDidntRun(['composer', 'run-script', 'post-create-project-cmd', '--no-interaction']);
});
