<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\StarterKitRelease;
use App\Exceptions\CouldNotResolveStarterKitRelease;
use Illuminate\Container\Attributes\Config;

final readonly class ResolveLatestStarterKitRelease
{
    public function __construct(
        private ResolveStarterKitRelease $resolveStarterKitRelease,
        #[Config('starter-kit.package')]
        private string $package,
    ) {}

    /**
     * @param  list<string>  $versions
     */
    public function handle(array $versions): StarterKitRelease
    {
        $latestVersion = $versions[0] ?? null;

        if ($latestVersion === null) {
            throw CouldNotResolveStarterKitRelease::noStableVersion($this->package);
        }

        return $this->resolveStarterKitRelease->handle($latestVersion);
    }
}
