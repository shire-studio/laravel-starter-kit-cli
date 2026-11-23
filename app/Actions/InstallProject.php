<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\StarterKitRelease;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

final readonly class InstallProject
{
    public function handle(StarterKitRelease $release, string $directory): void
    {
        $scripts = Arr::array((array) File::json("{$directory}/composer.json"), 'scripts', []);

        $commands = [
            ...$this->runScriptCommand('post-root-package-install', $scripts),
            ['composer', 'install', '--prefer-dist', '--no-interaction'],
            ...$this->runScriptCommand('post-create-project-cmd', $scripts),
        ];

        foreach ($commands as $command) {
            Process::path($directory)
                ->env(['COMPOSER_ROOT_VERSION' => $release->version])
                ->forever()
                ->run($command)
                ->throw();
        }
    }

    /**
     * @param  array<array-key, mixed>  $scripts
     * @return list<list<string>>
     */
    private function runScriptCommand(string $event, array $scripts): array
    {
        if (! array_key_exists($event, $scripts)) {
            return [];
        }

        return [['composer', 'run-script', $event, '--no-interaction']];
    }
}
