<?php

declare(strict_types=1);

namespace App\Commands\Concerns;

use Illuminate\Process\Exceptions\ProcessFailedException;

use function Laravel\Prompts\error;

trait ReportsFailedProcesses
{
    private function reportFailedProcess(ProcessFailedException $exception): int
    {
        $result = $exception->result;

        $this->output->write($result->errorOutput());
        $this->output->write($result->output());

        error("The command [{$result->command()}] failed with exit code {$result->exitCode()}.");

        return self::FAILURE;
    }
}
