<?php

declare(strict_types=1);

use App\Actions\ReadProposedRemovedFeatures;
use App\Data\StarterKitManifest;
use App\Data\UpdateDirectory;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->directory = temporaryDirectory();
});

afterEach(function (): void {
    File::deleteDirectory($this->directory);
});

it('reads the removed features the agent proposed', function (): void {
    createFiles($this->directory, [
        '.starter-kit-update/removed.json' => (string) json_encode(['Authentication', ' Teams ']),
    ]);

    expect(resolve(ReadProposedRemovedFeatures::class)->handle(new StarterKitManifest(starterKitRelease()), UpdateDirectory::in($this->directory)))->toBe(['Authentication', 'Teams']);
});

it('skips proposals that are not text', function (): void {
    createFiles($this->directory, [
        '.starter-kit-update/removed.json' => (string) json_encode(['Authentication', '', 42, ['name' => 'Teams']]),
    ]);

    expect(resolve(ReadProposedRemovedFeatures::class)->handle(new StarterKitManifest(starterKitRelease()), UpdateDirectory::in($this->directory)))->toBe(['Authentication']);
});

it('returns nothing when the agent proposed nothing', function (): void {
    expect(resolve(ReadProposedRemovedFeatures::class)->handle(new StarterKitManifest(starterKitRelease()), UpdateDirectory::in($this->directory)))->toBeEmpty();
});

it('returns nothing when the proposals are not a json list', function (): void {
    createFiles($this->directory, [
        '.starter-kit-update/removed.json' => 'Authentication',
    ]);

    expect(resolve(ReadProposedRemovedFeatures::class)->handle(new StarterKitManifest(starterKitRelease()), UpdateDirectory::in($this->directory)))->toBeEmpty();
});

it('skips features that are already recorded or proposed twice', function (): void {
    createFiles($this->directory, [
        '.starter-kit-update/removed.json' => (string) json_encode(['Teams', 'Authentication', 'Authentication']),
    ]);

    $manifest = new StarterKitManifest(starterKitRelease(), ['Teams']);

    expect(resolve(ReadProposedRemovedFeatures::class)->handle($manifest, UpdateDirectory::in($this->directory)))->toBe(['Authentication']);
});
