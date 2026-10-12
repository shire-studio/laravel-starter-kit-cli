<?php

declare(strict_types=1);

use App\Actions\CreateProject;
use Illuminate\Process\Exceptions\ProcessFailedException;
use Illuminate\Support\Facades\Process;

it('creates the project from the given release', function (): void {
    Process::fake();

    new CreateProject()->handle(starterKitRelease(), '/projects/example-app');

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

    new CreateProject()->handle(starterKitRelease(), '/projects/example-app');
})->throws(ProcessFailedException::class);
