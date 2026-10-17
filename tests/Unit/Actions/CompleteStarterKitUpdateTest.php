<?php

declare(strict_types=1);

use App\Actions\CompleteStarterKitUpdate;
use App\Actions\RecordStarterKitRelease;
use App\Data\UpdateDirectory;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->directory = temporaryDirectory();

    createFiles($this->directory, ['.starter-kit-update/PROMPT.md' => '# Update']);

    resolve(RecordStarterKitRelease::class)->handle(starterKitRelease('v1.3.0', 'a1b2c3'), $this->directory, ['Teams']);
    resolve(RecordStarterKitRelease::class)->handle(starterKitRelease(), "{$this->directory}/.starter-kit-update", ['Teams', 'Authentication']);
});

afterEach(function (): void {
    File::deleteDirectory($this->directory);
});

it('marks the project as updated with the staged manifest and removes the update directory', function (): void {
    resolve(CompleteStarterKitUpdate::class)->handle(UpdateDirectory::in($this->directory));

    expect(File::json("{$this->directory}/.starter-kit.json"))->toMatchArray([
        'version' => 'v1.4.0',
        'removed' => ['Teams', 'Authentication'],
    ])
        ->and("{$this->directory}/.starter-kit-update")->not->toBeDirectory();
});
