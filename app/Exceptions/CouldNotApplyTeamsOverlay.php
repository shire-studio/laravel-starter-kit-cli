<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Data\StarterKitRelease;
use RuntimeException;

final class CouldNotApplyTeamsOverlay extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly bool $releaseHasNoTeams = false,
    ) {
        parent::__construct($message);
    }

    public static function missing(StarterKitRelease $release): self
    {
        return new self("The {$release->kit->label()} starter kit {$release->version} has no teams variant.", releaseHasNoTeams: true);
    }

    public static function invalid(StarterKitRelease $release): self
    {
        return new self("The teams variant of the {$release->kit->label()} starter kit {$release->version} could not be read.");
    }

    public static function unsupportedVersion(StarterKitRelease $release, int $version): self
    {
        return new self("The teams variant of the {$release->kit->label()} starter kit {$release->version} uses overlay version {$version}, which this version of the CLI cannot apply. Upgrade it with composer global update shire-studio/laravel-starter-kit-cli.");
    }
}
