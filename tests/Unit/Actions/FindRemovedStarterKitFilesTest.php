<?php

declare(strict_types=1);

use App\Actions\FindRemovedStarterKitFiles;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->directory = temporaryDirectory();
});

afterEach(function (): void {
    File::deleteDirectory($this->directory);
});

it('finds the starter kit files the project no longer has', function (): void {
    createFiles("{$this->directory}/starter-kit", [
        '.env.example' => 'APP_NAME=Laravel',
        'app/Http/Controllers/Auth/LoginController.php' => '<?php',
        'app/Http/Controllers/Auth/RegisterController.php' => '<?php',
        'routes/web.php' => '<?php',
    ]);

    createFiles("{$this->directory}/project", [
        'routes/web.php' => '<?php',
        'routes/console.php' => '<?php',
    ]);

    $removedFiles = resolve(FindRemovedStarterKitFiles::class)->handle("{$this->directory}/starter-kit", "{$this->directory}/project");

    expect($removedFiles)->toBe([
        '.env.example',
        'app/Http/Controllers/Auth/LoginController.php',
        'app/Http/Controllers/Auth/RegisterController.php',
    ]);
});
