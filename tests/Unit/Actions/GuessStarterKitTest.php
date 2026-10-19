<?php

declare(strict_types=1);

use App\Actions\GuessStarterKit;
use App\Enums\Kit;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->directory = temporaryDirectory();
});

afterEach(function (): void {
    File::deleteDirectory($this->directory);
});

it('guesses the kit from the composer package name', function (): void {
    File::put("{$this->directory}/composer.json", (string) json_encode(['name' => 'shire-studio/laravel-starter-kit-svelte']));

    expect(resolve(GuessStarterKit::class)->handle($this->directory))->toBe(Kit::Svelte);
});

it('returns nothing when the package name is not a starter kit', function (): void {
    File::put("{$this->directory}/composer.json", (string) json_encode(['name' => 'acme/example-app']));

    expect(resolve(GuessStarterKit::class)->handle($this->directory))->toBeNull();
});

it('returns nothing when composer.json has no package name', function (): void {
    File::put("{$this->directory}/composer.json", (string) json_encode(['type' => 'project']));

    expect(resolve(GuessStarterKit::class)->handle($this->directory))->toBeNull();
});

it('returns nothing when composer.json is not an object', function (): void {
    File::put("{$this->directory}/composer.json", 'not json');

    expect(resolve(GuessStarterKit::class)->handle($this->directory))->toBeNull();
});

it('returns nothing when there is no composer.json', function (): void {
    expect(resolve(GuessStarterKit::class)->handle($this->directory))->toBeNull();
});
