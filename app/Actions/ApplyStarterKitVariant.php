<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\StarterKitRelease;
use App\Exceptions\CouldNotApplyTeamsOverlay;
use Illuminate\Container\Attributes\Config;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonException;

final readonly class ApplyStarterKitVariant
{
    private const int OVERLAY_VERSION = 1;

    public function __construct(
        private DeleteDirectory $deleteDirectory,
        #[Config('starter-kit.overlay_directory')]
        private string $overlayDirectory,
    ) {}

    public function handle(StarterKitRelease $release, string $directory, bool $teams): void
    {
        if ($teams) {
            $this->applyOverlay($release, "{$directory}/{$this->overlayDirectory}/teams", $directory);
        }

        $this->deleteDirectory->handle("{$directory}/{$this->overlayDirectory}");
    }

    private function applyOverlay(StarterKitRelease $release, string $overlayDirectory, string $directory): void
    {
        $deletedPaths = $this->deletedPaths($release, $overlayDirectory);

        $files = File::isDirectory("{$overlayDirectory}/files")
            ? File::allFiles("{$overlayDirectory}/files", hidden: true)
            : [];

        foreach ($files as $file) {
            $path = "{$directory}/{$file->getRelativePathname()}";

            File::ensureDirectoryExists(dirname($path));

            File::copy($file->getPathname(), $path);

            File::chmod($path, $file->getPerms() & 0o777);
        }

        foreach ($deletedPaths as $deletedPath) {
            File::delete("{$directory}/{$deletedPath}");
        }
    }

    /**
     * @return list<string>
     */
    private function deletedPaths(StarterKitRelease $release, string $overlayDirectory): array
    {
        $manifestPath = "{$overlayDirectory}/manifest.json";

        if (File::missing($manifestPath)) {
            throw CouldNotApplyTeamsOverlay::missing($release);
        }

        try {
            $manifest = json_decode(File::get($manifestPath), true, flags: JSON_THROW_ON_ERROR);

            if (! is_array($manifest)) {
                throw CouldNotApplyTeamsOverlay::invalid($release);
            }

            $version = Arr::integer($manifest, 'version');

            if ($version !== self::OVERLAY_VERSION) {
                throw CouldNotApplyTeamsOverlay::unsupportedVersion($release, $version);
            }

            $deletedPaths = [];

            foreach (Arr::array($manifest, 'deleted') as $deletedPath) {
                if (! is_string($deletedPath) || ! $this->isInsideProject($deletedPath)) {
                    throw CouldNotApplyTeamsOverlay::invalid($release);
                }

                $deletedPaths[] = $deletedPath;
            }

            return $deletedPaths;
        } catch (JsonException|InvalidArgumentException) {
            throw CouldNotApplyTeamsOverlay::invalid($release);
        }
    }

    private function isInsideProject(string $path): bool
    {
        if ($path === '' || Str::startsWith($path, '/')) {
            return false;
        }

        return ! in_array('..', explode('/', $path), true);
    }
}
