<?php

declare(strict_types=1);

use App\Actions\ReadStarterKitManifest;
use App\Actions\RecordStarterKitRelease;
use App\Exceptions\CouldNotReadStarterKitManifest;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->directory = temporaryDirectory();
});

afterEach(function (): void {
    File::deleteDirectory($this->directory);
});

it('reads the release the project was created from', function (): void {
    resolve(RecordStarterKitRelease::class)->handle(starterKitRelease(), $this->directory);

    $manifest = resolve(ReadStarterKitManifest::class)->handle($this->directory);

    expect($manifest->release)->toEqual(starterKitRelease())
        ->and($manifest->removedFeatures)->toBeEmpty();
});

it('reads the features the project has removed', function (): void {
    resolve(RecordStarterKitRelease::class)->handle(starterKitRelease(), $this->directory, ['Authentication']);

    $manifest = resolve(ReadStarterKitManifest::class)->handle($this->directory);

    expect($manifest->removedFeatures)->toBe(['Authentication']);
});

it('returns nothing when the project has no manifest', function (): void {
    expect(resolve(ReadStarterKitManifest::class)->handle($this->directory))->toBeNull();
});

it('reads the configured manifest file', function (): void {
    config(['starter-kit.manifest_filename' => 'starter-kit.lock']);

    resolve(RecordStarterKitRelease::class)->handle(starterKitRelease(), $this->directory);

    expect(resolve(ReadStarterKitManifest::class)->handle($this->directory)?->release->version)->toBe('v1.4.0');
});

it('throws when the manifest is not json', function (): void {
    File::put("{$this->directory}/.starter-kit.json", 'v1.4.0');

    resolve(ReadStarterKitManifest::class)->handle($this->directory);
})->throws(CouldNotReadStarterKitManifest::class, 'The [.starter-kit.json] file could not be read. Fix or delete it, then run update again.');

it('throws when the manifest is not an object', function (): void {
    File::put("{$this->directory}/.starter-kit.json", '"v1.4.0"');

    resolve(ReadStarterKitManifest::class)->handle($this->directory);
})->throws(CouldNotReadStarterKitManifest::class);

it('throws when the manifest has no version', function (): void {
    File::put("{$this->directory}/.starter-kit.json", (string) json_encode(['package' => 'shire-studio/laravel-starter-kit']));

    resolve(ReadStarterKitManifest::class)->handle($this->directory);
})->throws(CouldNotReadStarterKitManifest::class);

it('throws when a removed feature is not text', function (): void {
    File::put("{$this->directory}/.starter-kit.json", (string) json_encode([
        'package' => 'shire-studio/laravel-starter-kit',
        'version' => 'v1.4.0',
        'reference' => 'c31ae68197d092fbaac6ac68712b44f230e27850',
        'repository' => 'https://github.com/shire-studio/laravel-starter-kit.git',
        'removed' => [['name' => 'Authentication']],
    ]));

    resolve(ReadStarterKitManifest::class)->handle($this->directory);
})->throws(CouldNotReadStarterKitManifest::class);
