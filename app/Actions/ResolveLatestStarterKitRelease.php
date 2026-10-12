<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\StarterKitRelease;
use App\Exceptions\CouldNotResolveStarterKitRelease;
use Illuminate\Container\Attributes\Config;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Process;
use InvalidArgumentException;
use JsonException;

final readonly class ResolveLatestStarterKitRelease
{
    public function __construct(
        #[Config('starter-kit.package')]
        private string $package,
    ) {}

    public function handle(): StarterKitRelease
    {
        try {
            $version = $this->latestStableVersion();

            $metadata = $this->packageMetadata($version);

            return new StarterKitRelease(
                package: $this->package,
                version: $version,
                reference: Arr::string($metadata, 'source.reference'),
                repository: Arr::string($metadata, 'source.url'),
            );
        } catch (JsonException|InvalidArgumentException) {
            throw CouldNotResolveStarterKitRelease::invalidMetadata($this->package);
        }
    }

    private function latestStableVersion(): string
    {
        $version = collect(Arr::array($this->packageMetadata(), 'versions'))
            ->first(fn (mixed $version): bool => is_string($version) && preg_match('/^v?\d+\.\d+\.\d+$/', $version) === 1);

        if (! is_string($version)) {
            throw CouldNotResolveStarterKitRelease::noStableVersion($this->package);
        }

        return $version;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function packageMetadata(?string $version = null): array
    {
        $command = ['composer', 'show', $this->package, ...($version === null ? [] : [$version]), '--all', '--format=json'];

        $output = Process::path(sys_get_temp_dir())->run($command)->throw()->output();

        $metadata = json_decode($output, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($metadata)) {
            throw CouldNotResolveStarterKitRelease::invalidMetadata($this->package);
        }

        return $metadata;
    }
}
