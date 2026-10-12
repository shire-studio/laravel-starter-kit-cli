<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\StarterKitRelease;
use Illuminate\Support\Facades\Process;

final readonly class CreateProject
{
    public function __construct(
        private DeleteDirectory $deleteDirectory,
    ) {}

    public function handle(StarterKitRelease $release, string $directory): void
    {
        $result = Process::forever()
            ->run(['composer', 'create-project', $release->package, $directory, $release->version, '--prefer-dist', '--no-interaction']);

        if ($result->failed()) {
            $this->deleteDirectory->handle($directory);
        }

        $result->throw();
    }
}
