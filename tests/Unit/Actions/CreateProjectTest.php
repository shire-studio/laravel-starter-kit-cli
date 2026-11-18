<?php

declare(strict_types=1);

use App\Actions\CreateProject;
use App\Exceptions\CouldNotApplyTeamsOverlay;
use Illuminate\Process\Exceptions\ProcessFailedException;
use Illuminate\Process\FakeProcessResult;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

beforeEach(function (): void {
    $this->directory = temporaryDirectory().'/example-app';
});

afterEach(function (): void {
    File::deleteDirectory(dirname($this->directory));
});

function fakeCreateProject(string $directory, bool $withOverlay = true, array $overrides = []): void
{
    Process::fake($overrides + [
        "*'create-project'*" => function () use ($directory, $withOverlay): FakeProcessResult {
            createFiles($directory, [
                'composer.json' => (string) json_encode(['scripts' => ['post-create-project-cmd' => ['@php artisan key:generate --ansi']]]),
                'app/Models/User.php' => "<?php // base user\n",
            ]);

            if ($withOverlay) {
                createTeamsOverlay($directory, ['app/Models/Team.php' => "<?php // team\n"]);
            }

            return Process::result();
        },
        '*' => Process::result(),
    ]);
}

it('downloads the release without installing it or running its scripts', function (): void {
    fakeCreateProject($this->directory);

    resolve(CreateProject::class)->handle(starterKitRelease(), $this->directory, false);

    Process::assertRan([
        'composer',
        'create-project',
        'shire-studio/laravel-starter-kit-vue',
        $this->directory,
        'v1.4.0',
        '--prefer-dist',
        '--no-install',
        '--no-scripts',
        '--no-interaction',
    ]);

    expect("{$this->directory}/app/Models/Team.php")->not->toBeFile()
        ->and("{$this->directory}/.starter-kit")->not->toBeDirectory();
});

it('creates the project with teams', function (): void {
    fakeCreateProject($this->directory);

    resolve(CreateProject::class)->handle(starterKitRelease(), $this->directory, true);

    expect(File::get("{$this->directory}/app/Models/Team.php"))->toBe("<?php // team\n")
        ->and("{$this->directory}/.starter-kit")->not->toBeDirectory();
});

it('installs the project once the overlay is applied', function (): void {
    fakeCreateProject($this->directory, overrides: [
        "*'install'*" => function (PendingProcess $process): FakeProcessResult {
            expect("{$process->path}/app/Models/Team.php")->toBeFile()
                ->and("{$process->path}/.starter-kit")->not->toBeDirectory();

            return Process::result();
        },
    ]);

    resolve(CreateProject::class)->handle(starterKitRelease(), $this->directory, true);

    Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['composer', 'install', '--prefer-dist', '--no-interaction']
        && $process->path === $this->directory);
    Process::assertRan(['composer', 'run-script', 'post-create-project-cmd', '--no-interaction']);
});

it('throws when composer fails', function (): void {
    Process::fake([
        '*' => Process::result(exitCode: 1),
    ]);

    resolve(CreateProject::class)->handle(starterKitRelease(), $this->directory, false);
})->throws(ProcessFailedException::class);

it('removes the partially created project when composer fails', function (): void {
    Process::fake([
        '*' => function (): FakeProcessResult {
            createFiles($this->directory, ['composer.json' => '{}']);

            return Process::result(exitCode: 1);
        },
    ]);

    expect(fn () => resolve(CreateProject::class)->handle(starterKitRelease(), $this->directory, false))->toThrow(ProcessFailedException::class)
        ->and($this->directory)->not->toBeDirectory();
});

it('removes the project when the release has no teams overlay', function (): void {
    fakeCreateProject($this->directory, withOverlay: false);

    expect(fn () => resolve(CreateProject::class)->handle(starterKitRelease(), $this->directory, true))
        ->toThrow(CouldNotApplyTeamsOverlay::class, 'The Vue starter kit v1.4.0 has no teams variant.')
        ->and($this->directory)->not->toBeDirectory();

    Process::assertDidntRun(['composer', 'install', '--prefer-dist', '--no-interaction']);
});

it('removes the project when the install fails', function (): void {
    fakeCreateProject($this->directory, overrides: [
        "*'install'*" => Process::result(exitCode: 1),
    ]);

    expect(fn () => resolve(CreateProject::class)->handle(starterKitRelease(), $this->directory, false))->toThrow(ProcessFailedException::class)
        ->and($this->directory)->not->toBeDirectory();
});
