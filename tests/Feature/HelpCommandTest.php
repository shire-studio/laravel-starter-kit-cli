<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

it('lists the help command alongside the other commands', function (): void {
    Artisan::call('list');

    expect(Artisan::output())->toContain('Display help for a command');
});

it('shows the arguments and options of the new command', function (): void {
    $this->artisan('help', ['command_name' => 'new'])
        ->expectsOutputToContain('The name of the project')
        ->expectsOutputToContain('--pnpm')
        ->expectsOutputToContain('--git')
        ->expectsOutputToContain('--force')
        ->assertSuccessful();
});

it('shows the options of the update command', function (): void {
    $this->artisan('help', ['command_name' => 'update'])
        ->expectsOutputToContain('--agent')
        ->expectsOutputToContain('--force')
        ->assertSuccessful();
});
