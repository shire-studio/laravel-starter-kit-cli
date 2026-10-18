<?php

declare(strict_types=1);

use App\Data\StarterKitRelease;
use App\Enums\Kit;
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
    $temporaryDirectory = sys_get_temp_dir();
    $randomName = Str::random(16);

    $directory = "{$temporaryDirectory}/starter-kit-cli-tests/{$randomName}";

    File::ensureDirectoryExists($directory);

    return (string) realpath($directory);
}

function starterKitRelease(string $version = 'v1.4.0', string $reference = 'c31ae68197d092fbaac6ac68712b44f230e27850', Kit $kit = Kit::Vue): StarterKitRelease
{
    return new StarterKitRelease(
        kit: $kit,
        version: $version,
        reference: $reference,
        repository: 'https://github.com/shire-studio/laravel-starter-kit-vue.git',
    );
}

function createFiles(string $directory, array $files): void
{
    foreach ($files as $path => $contents) {
        File::ensureDirectoryExists(dirname("{$directory}/{$path}"));

        File::put("{$directory}/{$path}", $contents);
    }
}

function composerShowOutput(array $versions, string $reference = 'c31ae68197d092fbaac6ac68712b44f230e27850'): string
{
    return (string) json_encode([
        'name' => 'shire-studio/laravel-starter-kit-vue',
        'versions' => $versions,
        'source' => [
            'type' => 'git',
            'url' => 'https://github.com/shire-studio/laravel-starter-kit-vue.git',
            'reference' => $reference,
        ],
    ]);
}
