<?php

declare(strict_types=1);

namespace App\Data;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

final readonly class UpdateDirectory
{
    public function __construct(
        public string $projectDirectory,
        public string $name,
        public string $manifestFilename,
    ) {}

    public static function in(string $projectDirectory): self
    {
        return new self(
            projectDirectory: $projectDirectory,
            name: Config::string('starter-kit.update_directory'),
            manifestFilename: Config::string('starter-kit.manifest_filename'),
        );
    }

    public function path(): string
    {
        return "{$this->projectDirectory}/{$this->name}";
    }

    public function prompt(): string
    {
        return $this->file('PROMPT.md');
    }

    public function detectionPrompt(): string
    {
        return $this->file('DETECT.md');
    }

    public function changes(): string
    {
        return $this->file('changes.diff');
    }

    public function oldFiles(): string
    {
        return $this->file('old');
    }

    public function newFiles(): string
    {
        return $this->file('new');
    }

    public function removedFiles(): string
    {
        return $this->file('REMOVED.md');
    }

    public function commitLog(): string
    {
        return $this->file('COMMITS.md');
    }

    public function manifest(): string
    {
        return $this->file($this->manifestFilename);
    }

    public function removedFeatures(): string
    {
        return $this->file('removed.json');
    }

    public function detectedVersion(): string
    {
        return $this->file('detected-version');
    }

    public function releases(): string
    {
        return $this->file('releases');
    }

    public function relative(string $path): string
    {
        return Str::after($path, "{$this->projectDirectory}/");
    }

    private function file(string $filename): string
    {
        return "{$this->path()}/{$filename}";
    }
}
