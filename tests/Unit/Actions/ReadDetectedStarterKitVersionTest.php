<?php

declare(strict_types=1);

use App\Actions\ReadDetectedStarterKitVersion;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->directory = temporaryDirectory();
});

afterEach(function (): void {
    File::deleteDirectory($this->directory);
});

it('reads the version the agent detected', function (): void {
    createFiles($this->directory, ['.starter-kit-update/detected-version' => "v1.3.0\n"]);

    expect(resolve(ReadDetectedStarterKitVersion::class)->handle($this->directory))->toBe('v1.3.0');
});

it('returns nothing when the agent did not record a version', function (): void {
    expect(resolve(ReadDetectedStarterKitVersion::class)->handle($this->directory))->toBeNull();
});
