<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\Kit;
use App\Exceptions\CouldNotResolveStarterKitRelease;
use Illuminate\Support\Facades\Process;
use JsonException;

final readonly class FetchStarterKitMetadata
{
    /**
     * @return array<array-key, mixed>
     */
    public function handle(Kit $kit, ?string $version = null): array
    {
        $command = ['composer', 'show', $kit->package(), ...($version === null ? [] : [$version]), '--all', '--format=json'];

        $output = Process::path(sys_get_temp_dir())->run($command)->throw()->output();

        try {
            $metadata = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw CouldNotResolveStarterKitRelease::invalidMetadata($kit);
        }

        if (! is_array($metadata)) {
            throw CouldNotResolveStarterKitRelease::invalidMetadata($kit);
        }

        return $metadata;
    }
}
