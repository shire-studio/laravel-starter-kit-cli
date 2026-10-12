<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\StarterKitRelease;
use App\Exceptions\CouldNotResolveStarterKitRelease;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Process;

final readonly class ResolveLatestStarterKitRelease
{
    private const string PACKAGE = 'shire-studio/laravel-starter-kit';

    public function handle(): StarterKitRelease
    {
        $version = $this->latestStableVersion();

        $package = $this->showPackage($version);

        return new StarterKitRelease(
            package: self::PACKAGE,
            version: $version,
            reference: Arr::string($package, 'source.reference'),
            repository: Arr::string($package, 'source.url'),
        );
    }

    private function latestStableVersion(): string
    {
        $version = collect(Arr::array($this->showPackage(), 'versions'))
            ->first(fn (mixed $version): bool => is_string($version) && preg_match('/^v?\d+\.\d+\.\d+$/', $version) === 1);

        if (! is_string($version)) {
            throw CouldNotResolveStarterKitRelease::noStableVersion(self::PACKAGE);
        }

        return $version;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function showPackage(?string $version = null): array
    {
        $command = ['composer', 'show', self::PACKAGE, ...($version === null ? [] : [$version]), '--all', '--format=json'];

        $output = Process::run($command)->throw()->output();

        $package = json_decode($output, true, flags: JSON_THROW_ON_ERROR);

        assert(is_array($package));

        return $package;
    }
}
