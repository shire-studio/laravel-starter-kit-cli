<?php

declare(strict_types=1);

use App\Actions\ApplyStarterKitVariant;
use App\Exceptions\CouldNotApplyTeamsOverlay;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->directory = temporaryDirectory();

    createFiles($this->directory, [
        'app/Models/User.php' => "<?php // base user\n",
        'routes/web.php' => "<?php // base routes\n",
        'resources/js/pages/Dashboard.vue' => "<template>base</template>\n",
    ]);
});

afterEach(function (): void {
    File::deleteDirectory($this->directory);
});

it('applies the teams overlay', function (): void {
    createTeamsOverlay($this->directory, [
        'app/Models/User.php' => "<?php // team user\n",
        'app/Models/Team.php' => "<?php // team\n",
        'bin/teams' => "#!/bin/sh\n",
        '.github/workflows/teams.yml' => "name: teams\n",
    ], ['resources/js/pages/Dashboard.vue']);

    chmod("{$this->directory}/.starter-kit/teams/files/bin/teams", 0o755);

    resolve(ApplyStarterKitVariant::class)->handle(starterKitRelease(), $this->directory, true);

    expect(File::get("{$this->directory}/app/Models/User.php"))->toBe("<?php // team user\n")
        ->and(File::get("{$this->directory}/app/Models/Team.php"))->toBe("<?php // team\n")
        ->and(File::get("{$this->directory}/.github/workflows/teams.yml"))->toBe("name: teams\n")
        ->and(File::get("{$this->directory}/routes/web.php"))->toBe("<?php // base routes\n")
        ->and(fileperms("{$this->directory}/bin/teams") & 0o777)->toBe(0o755)
        ->and("{$this->directory}/resources/js/pages/Dashboard.vue")->not->toBeFile()
        ->and("{$this->directory}/.starter-kit")->not->toBeDirectory();
});

it('gives an overlay file the mode it has in the overlay', function (): void {
    createFiles($this->directory, ['artisan' => "<?php // base artisan\n"]);

    chmod("{$this->directory}/artisan", 0o755);

    createTeamsOverlay($this->directory, ['artisan' => "<?php // team artisan\n"]);

    chmod("{$this->directory}/.starter-kit/teams/files/artisan", 0o644);

    resolve(ApplyStarterKitVariant::class)->handle(starterKitRelease(), $this->directory, true);

    expect(fileperms("{$this->directory}/artisan") & 0o777)->toBe(0o644);
});

it('ignores a deleted path the release does not have', function (): void {
    createTeamsOverlay($this->directory, deleted: ['config/missing.php']);

    resolve(ApplyStarterKitVariant::class)->handle(starterKitRelease(), $this->directory, true);

    expect("{$this->directory}/.starter-kit")->not->toBeDirectory();
});

it('drops the teams overlay', function (): void {
    createTeamsOverlay($this->directory, ['app/Models/User.php' => "<?php // team user\n"], ['routes/web.php']);

    resolve(ApplyStarterKitVariant::class)->handle(starterKitRelease(), $this->directory, false);

    expect(File::get("{$this->directory}/app/Models/User.php"))->toBe("<?php // base user\n")
        ->and("{$this->directory}/routes/web.php")->toBeFile()
        ->and("{$this->directory}/app/Models/Team.php")->not->toBeFile()
        ->and("{$this->directory}/.starter-kit")->not->toBeDirectory();
});

it('leaves a release without an overlay as it is', function (): void {
    resolve(ApplyStarterKitVariant::class)->handle(starterKitRelease(), $this->directory, false);

    expect(File::get("{$this->directory}/app/Models/User.php"))->toBe("<?php // base user\n");
});

it('uses the configured overlay directory', function (): void {
    config(['starter-kit.overlay_directory' => '.variants']);

    createFiles($this->directory, [
        '.variants/teams/files/app/Models/Team.php' => "<?php // team\n",
        '.variants/teams/manifest.json' => '{"version": 1, "deleted": []}',
    ]);

    resolve(ApplyStarterKitVariant::class)->handle(starterKitRelease(), $this->directory, true);

    expect("{$this->directory}/app/Models/Team.php")->toBeFile()
        ->and("{$this->directory}/.variants")->not->toBeDirectory();
});

it('throws when the release has no teams overlay', function (): void {
    resolve(ApplyStarterKitVariant::class)->handle(starterKitRelease(), $this->directory, true);
})->throws(CouldNotApplyTeamsOverlay::class, 'The Vue starter kit v1.4.0 has no teams variant.');

it('rejects an overlay version it does not know without changing anything', function (): void {
    createTeamsOverlay($this->directory, ['app/Models/User.php' => "<?php // team user\n"], ['routes/web.php'], version: 2);

    expect(fn () => resolve(ApplyStarterKitVariant::class)->handle(starterKitRelease(), $this->directory, true))
        ->toThrow(CouldNotApplyTeamsOverlay::class, 'The teams variant of the Vue starter kit v1.4.0 uses overlay version 2, which this version of the CLI cannot apply. Upgrade it with composer global update shire-studio/laravel-starter-kit-cli.')
        ->and(File::get("{$this->directory}/app/Models/User.php"))->toBe("<?php // base user\n")
        ->and("{$this->directory}/routes/web.php")->toBeFile();
});

it('throws when the overlay manifest is not json', function (): void {
    createFiles($this->directory, ['.starter-kit/teams/manifest.json' => 'version 1']);

    resolve(ApplyStarterKitVariant::class)->handle(starterKitRelease(), $this->directory, true);
})->throws(CouldNotApplyTeamsOverlay::class, 'The teams variant of the Vue starter kit v1.4.0 could not be read.');

it('throws when the overlay manifest is not an object', function (): void {
    createFiles($this->directory, ['.starter-kit/teams/manifest.json' => '1']);

    resolve(ApplyStarterKitVariant::class)->handle(starterKitRelease(), $this->directory, true);
})->throws(CouldNotApplyTeamsOverlay::class, 'could not be read');

it('throws when the overlay manifest has no version', function (): void {
    createFiles($this->directory, [
        '.starter-kit/teams/files/app/Models/Team.php' => "<?php // team\n",
        '.starter-kit/teams/manifest.json' => '{"deleted": []}',
    ]);

    resolve(ApplyStarterKitVariant::class)->handle(starterKitRelease(), $this->directory, true);
})->throws(CouldNotApplyTeamsOverlay::class, 'could not be read');

it('throws when the overlay manifest has no deleted list', function (): void {
    createFiles($this->directory, [
        '.starter-kit/teams/files/app/Models/Team.php' => "<?php // team\n",
        '.starter-kit/teams/manifest.json' => '{"version": 1}',
    ]);

    resolve(ApplyStarterKitVariant::class)->handle(starterKitRelease(), $this->directory, true);
})->throws(CouldNotApplyTeamsOverlay::class, 'could not be read');

it('applies an overlay that only deletes files', function (): void {
    createFiles($this->directory, ['.starter-kit/teams/manifest.json' => '{"version": 1, "deleted": ["routes/web.php"]}']);

    resolve(ApplyStarterKitVariant::class)->handle(starterKitRelease(), $this->directory, true);

    expect("{$this->directory}/routes/web.php")->not->toBeFile()
        ->and(File::get("{$this->directory}/app/Models/User.php"))->toBe("<?php // base user\n")
        ->and("{$this->directory}/.starter-kit")->not->toBeDirectory();
});

it('throws when a deleted path is not text', function (): void {
    createTeamsOverlay($this->directory, ['app/Models/Team.php' => "<?php // team\n"], [['path' => 'routes/web.php']]);

    resolve(ApplyStarterKitVariant::class)->handle(starterKitRelease(), $this->directory, true);
})->throws(CouldNotApplyTeamsOverlay::class, 'could not be read');

it('refuses a deleted path outside the project without changing anything', function (): void {
    createTeamsOverlay($this->directory, ['app/Models/User.php' => "<?php // team user\n"], ['routes/web.php', '../outside.php']);

    expect(fn () => resolve(ApplyStarterKitVariant::class)->handle(starterKitRelease(), $this->directory, true))
        ->toThrow(CouldNotApplyTeamsOverlay::class, 'could not be read')
        ->and(File::get("{$this->directory}/app/Models/User.php"))->toBe("<?php // base user\n")
        ->and("{$this->directory}/routes/web.php")->toBeFile();
});

it('refuses an absolute deleted path', function (): void {
    createTeamsOverlay($this->directory, ['app/Models/Team.php' => "<?php // team\n"], ["{$this->directory}/routes/web.php"]);

    expect(fn () => resolve(ApplyStarterKitVariant::class)->handle(starterKitRelease(), $this->directory, true))
        ->toThrow(CouldNotApplyTeamsOverlay::class, 'could not be read')
        ->and("{$this->directory}/routes/web.php")->toBeFile();
});

it('refuses an empty deleted path', function (): void {
    createTeamsOverlay($this->directory, ['app/Models/Team.php' => "<?php // team\n"], ['']);

    resolve(ApplyStarterKitVariant::class)->handle(starterKitRelease(), $this->directory, true);
})->throws(CouldNotApplyTeamsOverlay::class, 'could not be read');
