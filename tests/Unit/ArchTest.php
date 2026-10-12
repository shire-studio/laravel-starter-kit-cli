<?php

declare(strict_types=1);

arch()->preset()->php();
arch()->preset()->strict();
arch()->preset()->security()->ignoring([
    'assert',
]);

arch('actions')
    ->expect('App\Actions')
    ->toBeReadonly()
    ->toHaveMethod('handle')
    ->not->toUse([
        'App\Commands',
        'Illuminate\Console',
        'Laravel\Prompts',
        'Symfony\Component\Console',
    ]);

arch('commands')
    ->expect('App\Commands')
    ->not->toBeUsed();
