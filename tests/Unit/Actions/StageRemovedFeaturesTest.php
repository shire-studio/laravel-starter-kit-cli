<?php

declare(strict_types=1);

use App\Actions\StageRemovedFeatures;
use App\Data\UpdateDirectory;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->directory = temporaryDirectory();

    createFiles($this->directory, ['.starter-kit-update/removed.json' => '["Authentication", "Billing"]']);
});

afterEach(function (): void {
    File::deleteDirectory($this->directory);
});

it('records the removed features in the staged manifest', function (): void {
    resolve(StageRemovedFeatures::class)->handle(UpdateDirectory::in($this->directory), starterKitRelease(), ['Teams', 'Authentication']);

    expect(File::json("{$this->directory}/.starter-kit-update/.starter-kit.json"))->toMatchArray([
        'version' => 'v1.4.0',
        'removed' => ['Teams', 'Authentication'],
    ])
        ->and("{$this->directory}/.starter-kit-update/removed.json")->not->toBeFile()
        ->and("{$this->directory}/.starter-kit.json")->not->toBeFile();
});
