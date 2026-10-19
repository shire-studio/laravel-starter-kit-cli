<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\StarterKitRelease;
use App\Enums\Kit;
use App\Exceptions\CouldNotResolveStarterKitRelease;

final readonly class ResolveLatestStarterKitRelease
{
    public function __construct(
        private ResolveStarterKitRelease $resolveStarterKitRelease,
    ) {}

    /**
     * @param  list<string>  $versions
     */
    public function handle(Kit $kit, array $versions): StarterKitRelease
    {
        $latestVersion = $versions[0] ?? null;

        if ($latestVersion === null) {
            throw CouldNotResolveStarterKitRelease::noStableVersion($kit);
        }

        return $this->resolveStarterKitRelease->handle($kit, $latestVersion);
    }
}
