<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class CouldNotAddTeams extends RuntimeException
{
    public static function interrupted(Throwable $previous): self
    {
        $reason = Str::finish($previous->getMessage(), '.');

        return new self("Adding teams stopped partway: {$reason} Some team changes may already be in the project. See them with git status, and undo them with git restore and git clean if you want to start again.", previous: $previous);
    }
}
