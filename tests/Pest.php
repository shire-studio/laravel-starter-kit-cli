<?php

declare(strict_types=1);

use App\Data\StarterKitRelease;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Tests\TestCase;

pest()->tia()
    ->locally();

pest()->extend(TestCase::class)
    ->beforeEach(function (): void {
        $this->app->detectEnvironment(fn (): string => 'testing');

        Process::preventStrayProcesses();

        $this->freezeTime();
    })
    ->in('Feature', 'Unit');

function temporaryDirectory(): string
{
    $directory = sys_get_temp_dir().'/starter-kit-cli-tests/'.Str::random(16);

    File::ensureDirectoryExists($directory);

    return (string) realpath($directory);
}

function starterKitRelease(): StarterKitRelease
{
    return new StarterKitRelease(
        package: 'shire-studio/laravel-starter-kit',
        version: 'v1.4.0',
        reference: 'c31ae68197d092fbaac6ac68712b44f230e27850',
        repository: 'https://github.com/shire-studio/laravel-starter-kit.git',
    );
}

/**
 * @param  list<string>  $versions
 */
function composerShowOutput(array $versions, string $reference = 'c31ae68197d092fbaac6ac68712b44f230e27850'): string
{
    return (string) json_encode([
        'name' => 'shire-studio/laravel-starter-kit',
        'versions' => $versions,
        'source' => [
            'type' => 'git',
            'url' => 'https://github.com/shire-studio/laravel-starter-kit.git',
            'reference' => $reference,
        ],
    ]);
}
