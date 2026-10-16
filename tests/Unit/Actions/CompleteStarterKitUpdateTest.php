<?php

declare(strict_types=1);

use App\Actions\CompleteStarterKitUpdate;
use App\Data\StarterKitManifest;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->directory = temporaryDirectory();

    createFiles($this->directory, ['.starter-kit-update/PROMPT.md' => '# Update']);
});

afterEach(function (): void {
    File::deleteDirectory($this->directory);
});

it('marks the project as updated and removes the update directory', function (): void {
    $manifest = new StarterKitManifest(starterKitRelease('v1.3.0', 'a1b2c3'), ['Teams']);

    resolve(CompleteStarterKitUpdate::class)->handle($manifest, starterKitRelease(), $this->directory, ['Authentication']);

    expect(File::json("{$this->directory}/.starter-kit.json"))->toMatchArray([
        'version' => 'v1.4.0',
        'removed' => ['Teams', 'Authentication'],
    ])
        ->and("{$this->directory}/.starter-kit-update")->not->toBeDirectory();
});

it('keeps the recorded removed features when no new ones are found', function (): void {
    $manifest = new StarterKitManifest(starterKitRelease('v1.3.0', 'a1b2c3'), ['Teams']);

    resolve(CompleteStarterKitUpdate::class)->handle($manifest, starterKitRelease(), $this->directory);

    expect(File::json("{$this->directory}/.starter-kit.json")['removed'])->toBe(['Teams']);
});
