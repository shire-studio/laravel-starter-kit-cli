<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\UpdateDirectory;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

final readonly class IgnoreUpdateDirectory
{
    public function handle(UpdateDirectory $updateDirectory): void
    {
        $excludePath = $this->git($updateDirectory, ['rev-parse', '--path-format=absolute', '--git-path', 'info/exclude']);
        $projectPrefix = $this->git($updateDirectory, ['rev-parse', '--show-prefix']);

        $pattern = "/{$projectPrefix}{$updateDirectory->name}/";

        $excludedPatterns = File::exists($excludePath) ? File::get($excludePath) : '';

        if (in_array($pattern, explode("\n", $excludedPatterns), true)) {
            return;
        }

        $needsNewLine = $excludedPatterns !== '' && ! Str::endsWith($excludedPatterns, "\n");

        $separator = $needsNewLine ? "\n" : '';

        File::ensureDirectoryExists(dirname($excludePath));

        File::append($excludePath, "{$separator}{$pattern}\n");
    }

    /**
     * @param  list<string>  $arguments
     */
    private function git(UpdateDirectory $updateDirectory, array $arguments): string
    {
        return mb_trim(Process::path($updateDirectory->projectDirectory)
            ->run(['git', ...$arguments])
            ->throw()
            ->output());
    }
}
