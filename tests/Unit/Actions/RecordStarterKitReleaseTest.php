<?php

declare(strict_types=1);

use App\Actions\RecordStarterKitRelease;
use App\Data\StarterKitManifest;
use App\Data\StarterKitRelease;
use App\Enums\Kit;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->directory = temporaryDirectory();
});

afterEach(function (): void {
    File::deleteDirectory($this->directory);
});

it('records the release the project was created from', function (): void {
    resolve(RecordStarterKitRelease::class)->handle(new StarterKitManifest(starterKitRelease()), $this->directory);

    expect(File::get("{$this->directory}/.starter-kit.json"))->toBe(<<<'JSON'
        {
            "package": "shire-studio/laravel-starter-kit-vue",
            "version": "v1.4.0",
            "reference": "c31ae68197d092fbaac6ac68712b44f230e27850",
            "repository": "https://github.com/shire-studio/laravel-starter-kit-vue.git",
            "teams": false
        }

        JSON);
});

it('records the release in the configured manifest file', function (): void {
    config(['starter-kit.manifest_filename' => 'starter-kit.lock']);

    resolve(RecordStarterKitRelease::class)->handle(new StarterKitManifest(starterKitRelease()), $this->directory);

    expect("{$this->directory}/starter-kit.lock")->toBeFile()
        ->and("{$this->directory}/.starter-kit.json")->not->toBeFile();
});

it('records the features the project has removed', function (): void {
    resolve(RecordStarterKitRelease::class)->handle(new StarterKitManifest(starterKitRelease(), ['Authentication (login, registration and two factor)']), $this->directory);

    expect(File::json("{$this->directory}/.starter-kit.json"))->toMatchArray([
        'version' => 'v1.4.0',
        'removed' => ['Authentication (login, registration and two factor)'],
    ]);
});

it('leaves out the reference when it is unknown', function (): void {
    $release = new StarterKitRelease(
        kit: Kit::Vue,
        version: 'v1.5.0',
        reference: null,
        repository: 'https://github.com/shire-studio/laravel-starter-kit-vue.git',
    );

    resolve(RecordStarterKitRelease::class)->handle(new StarterKitManifest($release), $this->directory);

    expect(File::json("{$this->directory}/.starter-kit.json"))->toBe([
        'package' => 'shire-studio/laravel-starter-kit-vue',
        'version' => 'v1.5.0',
        'repository' => 'https://github.com/shire-studio/laravel-starter-kit-vue.git',
        'teams' => false,
    ]);
});

it('records that the project has teams', function (): void {
    resolve(RecordStarterKitRelease::class)->handle(new StarterKitManifest(starterKitRelease(), ['Billing'], teams: true), $this->directory);

    expect(File::json("{$this->directory}/.starter-kit.json"))->toMatchArray([
        'teams' => true,
        'removed' => ['Billing'],
    ]);
});
