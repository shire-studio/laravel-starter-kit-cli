<?php

declare(strict_types=1);

use App\Actions\RecordStarterKitRelease;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->directory = temporaryDirectory();
});

afterEach(function (): void {
    File::deleteDirectory($this->directory);
});

it('records the release the project was created from', function (): void {
    new RecordStarterKitRelease()->handle(starterKitRelease(), $this->directory);

    expect(File::get("{$this->directory}/.starter-kit.json"))->toBe(<<<'JSON'
        {
            "package": "shire-studio/laravel-starter-kit",
            "version": "v1.4.0",
            "reference": "c31ae68197d092fbaac6ac68712b44f230e27850",
            "repository": "https://github.com/shire-studio/laravel-starter-kit.git"
        }

        JSON);
});
