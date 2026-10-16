<?php

declare(strict_types=1);

namespace App\Actions;

use App\Exceptions\CouldNotResolveStarterKitRelease;
use Illuminate\Container\Attributes\Config;
use Illuminate\Support\Facades\Process;
use JsonException;

final readonly class FetchStarterKitMetadata
{
    public function __construct(
        #[Config('starter-kit.package')]
        private string $package,
    ) {}

    /**
     * @return array<array-key, mixed>
     */
    public function handle(?string $version = null): array
    {
        $command = ['composer', 'show', $this->package, ...($version === null ? [] : [$version]), '--all', '--format=json'];

        $output = Process::path(sys_get_temp_dir())->run($command)->throw()->output();

        try {
            $metadata = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw CouldNotResolveStarterKitRelease::invalidMetadata($this->package);
        }

        if (! is_array($metadata)) {
            throw CouldNotResolveStarterKitRelease::invalidMetadata($this->package);
        }

        return $metadata;
    }
}
