<?php

declare(strict_types=1);

use App\Actions\PrepareStarterKitDetection;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->directory = temporaryDirectory();
});

afterEach(function (): void {
    File::deleteDirectory($this->directory);
});

it('writes the prompt for detecting the starter kit version', function (): void {
    resolve(PrepareStarterKitDetection::class)->handle($this->directory, starterKitRelease(), ['v1.4.0', 'v1.3.0']);

    expect(File::get("{$this->directory}/.starter-kit-update/DETECT.md"))
        ->toContain('# Find the starter kit version this project was created from')
        ->toContain("- v1.4.0\n- v1.3.0\n")
        ->toContain('composer create-project shire-studio/laravel-starter-kit .starter-kit-update/releases/<version> <version> --no-install --no-scripts')
        ->toContain('`.starter-kit-update/detected-version`');
});

it('replaces a stale update directory', function (): void {
    createFiles($this->directory, ['.starter-kit-update/detected-version' => 'v1.0.0']);

    resolve(PrepareStarterKitDetection::class)->handle($this->directory, starterKitRelease(), ['v1.4.0']);

    expect("{$this->directory}/.starter-kit-update/detected-version")->not->toBeFile();
});
