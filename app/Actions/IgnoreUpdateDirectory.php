<?php

declare(strict_types=1);

namespace App\Actions;

use Illuminate\Container\Attributes\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

final readonly class IgnoreUpdateDirectory
{
    public function __construct(
        #[Config('starter-kit.update_directory')]
        private string $updateDirectory,
    ) {}

    public function handle(string $directory): void
    {
        $excludePath = mb_trim(Process::path($directory)
            ->run(['git', 'rev-parse', '--path-format=absolute', '--git-path', 'info/exclude'])
            ->throw()
            ->output());

        $pattern = "/{$this->updateDirectory}/";

        $excludedPatterns = File::exists($excludePath) ? File::get($excludePath) : '';

        if (in_array($pattern, explode("\n", $excludedPatterns), true)) {
            return;
        }

        $needsNewLine = $excludedPatterns !== '' && ! Str::endsWith($excludedPatterns, "\n");

        $separator = $needsNewLine ? "\n" : '';

        File::ensureDirectoryExists(dirname($excludePath));

        File::append($excludePath, "{$separator}{$pattern}\n");
    }
}
