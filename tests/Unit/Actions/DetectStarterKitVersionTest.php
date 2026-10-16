<?php

declare(strict_types=1);

use App\Actions\DetectStarterKitVersion;
use App\Enums\Agent;
use Illuminate\Process\FakeProcessResult;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

beforeEach(function (): void {
    $this->directory = temporaryDirectory();
});

afterEach(function (): void {
    File::deleteDirectory($this->directory);
});

it('asks the agent to detect the starter kit version', function (): void {
    Process::fake([
        '*' => function (): FakeProcessResult {
            File::put("{$this->directory}/.starter-kit-update/detected-version", "v1.3.0\n");

            return Process::result();
        },
    ]);

    $detectedVersion = resolve(DetectStarterKitVersion::class)->handle(Agent::ClaudeCode, $this->directory, starterKitRelease(), ['v1.4.0', 'v1.3.0']);

    expect($detectedVersion)->toBe('v1.3.0')
        ->and(File::get("{$this->directory}/.starter-kit-update/DETECT.md"))
        ->toContain('# Find the starter kit version this project was created from')
        ->toContain("- v1.4.0\n- v1.3.0\n")
        ->toContain('composer create-project shire-studio/laravel-starter-kit .starter-kit-update/releases/<version> <version> --no-install --no-scripts')
        ->toContain('`.starter-kit-update/detected-version`');

    Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['claude', 'Read .starter-kit-update/DETECT.md and follow the instructions in it.']
        && $process->path === $this->directory);
});

it('returns nothing when the agent does not record a version', function (): void {
    Process::fake();

    expect(resolve(DetectStarterKitVersion::class)->handle(Agent::ClaudeCode, $this->directory, starterKitRelease(), ['v1.4.0']))->toBeNull();
});

it('replaces a stale update directory', function (): void {
    createFiles($this->directory, ['.starter-kit-update/detected-version' => 'v1.0.0']);

    Process::fake();

    expect(resolve(DetectStarterKitVersion::class)->handle(Agent::ClaudeCode, $this->directory, starterKitRelease(), ['v1.4.0']))->toBeNull();
});
