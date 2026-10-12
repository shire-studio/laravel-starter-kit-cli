<?php

declare(strict_types=1);

use App\Actions\CreateProject;
use Illuminate\Process\Exceptions\ProcessFailedException;
use Illuminate\Process\FakeProcessResult;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

it('creates the project from the given release', function (): void {
    Process::fake();

    resolve(CreateProject::class)->handle(starterKitRelease(), '/projects/example-app');

    Process::assertRan([
        'composer',
        'create-project',
        'shire-studio/laravel-starter-kit',
        '/projects/example-app',
        'v1.4.0',
        '--prefer-dist',
        '--no-interaction',
    ]);
});

it('throws when composer fails', function (): void {
    Process::fake([
        '*' => Process::result(exitCode: 1),
    ]);

    resolve(CreateProject::class)->handle(starterKitRelease(), '/projects/example-app');
})->throws(ProcessFailedException::class);

it('removes the partially created project when composer fails', function (): void {
    $directory = temporaryDirectory();

    Process::fake([
        '*' => function () use ($directory): FakeProcessResult {
            File::put("{$directory}/composer.json", '{}');

            return Process::result(exitCode: 1);
        },
    ]);

    expect(fn () => resolve(CreateProject::class)->handle(starterKitRelease(), $directory))->toThrow(ProcessFailedException::class)
        ->and($directory)->not->toBeDirectory();
});
