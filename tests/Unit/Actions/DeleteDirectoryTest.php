<?php

declare(strict_types=1);

use App\Actions\DeleteDirectory;
use Illuminate\Support\Facades\File;

it('deletes the directory and everything in it', function (): void {
    $directory = temporaryDirectory();

    File::put("{$directory}/old-file.txt", 'old');

    resolve(DeleteDirectory::class)->handle($directory);

    expect($directory)->not->toBeDirectory();
});
