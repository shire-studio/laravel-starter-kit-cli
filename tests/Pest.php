<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Process;
use Tests\TestCase;

pest()->tia()
    ->locally();

pest()->extend(TestCase::class)
    ->beforeEach(function (): void {
        Process::preventStrayProcesses();

        $this->freezeTime();
    })
    ->in('Feature', 'Unit');
