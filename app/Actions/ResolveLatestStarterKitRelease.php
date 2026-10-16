<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\StarterKitRelease;
use App\Exceptions\CouldNotResolveStarterKitRelease;
use Illuminate\Container\Attributes\Config;

final readonly class ResolveLatestStarterKitRelease
{
    public function __construct(
        private ListStableStarterKitVersions $listStableStarterKitVersions,
        private ResolveStarterKitRelease $resolveStarterKitRelease,
        #[Config('starter-kit.package')]
        private string $package,
    ) {}

    public function handle(): StarterKitRelease
    {
        $latestVersion = $this->listStableStarterKitVersions->handle()[0] ?? null;

        if ($latestVersion === null) {
            throw CouldNotResolveStarterKitRelease::noStableVersion($this->package);
        }

        return $this->resolveStarterKitRelease->handle($latestVersion);
    }
}
