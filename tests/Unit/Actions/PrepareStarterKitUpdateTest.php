<?php

declare(strict_types=1);

use App\Actions\PrepareStarterKitUpdate;
use App\Data\StarterKitManifest;
use App\Data\UpdateDirectory;
use Illuminate\Process\Exceptions\ProcessFailedException;
use Illuminate\Process\FakeProcessResult;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->directory = temporaryDirectory();
    $this->updateDirectory = "{$this->directory}/.starter-kit-update";
    $this->workingDirectory = sys_get_temp_dir().'/laravel-starter-kit-cli/'.basename($this->directory);

    createFiles($this->directory, [
        'composer.json' => "{\"version\": 1}\n",
        'routes/web.php' => "<?php // project routes\n",
    ]);

    Str::createRandomStringsUsing(fn (): string => basename($this->directory));

    Process::preventStrayProcesses(false);
});

afterEach(function (): void {
    Str::createRandomStringsNormally();

    File::deleteDirectory($this->directory);
});

function fakeStarterKitDownloads(array $overrides = []): void
{
    Process::fake($overrides + [
        "*'create-project'*" => function (PendingProcess $process): FakeProcessResult {
            createFiles($process->command[3], $process->command[4] === 'v1.3.0' ? [
                'composer.json' => "{\"version\": 1}\n",
                'app/Http/Controllers/Auth/LoginController.php' => "<?php // login\n",
                'config/legacy.php' => "<?php // legacy\n",
                'routes/web.php' => "<?php // version 1\n",
            ] : [
                'composer.json' => "{\"version\": 2}\n",
                'app/Http/Controllers/Auth/LoginController.php' => "<?php // login\n",
                'app/Actions/NewAction.php' => "<?php // new\n",
                'routes/web.php' => "<?php // version 2\n",
            ]);

            return Process::result();
        },
        "*'clone'*" => Process::result(exitCode: 128),
    ]);
}

it('prepares the update', function (): void {
    fakeStarterKitDownloads();

    $update = resolve(PrepareStarterKitUpdate::class)->handle(new StarterKitManifest(starterKitRelease('v1.3.0', 'a1b2c3')), starterKitRelease(), UpdateDirectory::in($this->directory));

    expect($update->from->version)->toBe('v1.3.0')
        ->and($update->to->version)->toBe('v1.4.0')
        ->and($update->diff->changedFileCount())->toBe(4)
        ->and($update->removedFiles)->toBe(['app/Http/Controllers/Auth/LoginController.php', 'config/legacy.php'])
        ->and(File::get("{$this->updateDirectory}/changes.diff"))->toContain('diff --git a/routes/web.php b/routes/web.php')
        ->and(File::get("{$this->updateDirectory}/old/routes/web.php"))->toBe("<?php // version 1\n")
        ->and(File::get("{$this->updateDirectory}/old/config/legacy.php"))->toBe("<?php // legacy\n")
        ->and(File::get("{$this->updateDirectory}/new/routes/web.php"))->toBe("<?php // version 2\n")
        ->and(File::get("{$this->updateDirectory}/new/app/Actions/NewAction.php"))->toBe("<?php // new\n")
        ->and("{$this->updateDirectory}/old/app/Http/Controllers/Auth/LoginController.php")->not->toBeFile()
        ->and("{$this->updateDirectory}/COMMITS.md")->not->toBeFile()
        ->and(File::json("{$this->updateDirectory}/.starter-kit.json"))->toMatchArray(['version' => 'v1.4.0'])
        ->and(File::get("{$this->updateDirectory}/REMOVED.md"))
        ->toContain("## app/Http/Controllers/Auth\n\n- app/Http/Controllers/Auth/LoginController.php\n")
        ->toContain("## config\n\n- config/legacy.php\n")
        ->and(File::get("{$this->updateDirectory}/PROMPT.md"))
        ->toContain('# Update this project to starter kit v1.4.0')
        ->toContain('Vue starter kit ([shire-studio/laravel-starter-kit-vue](https://github.com/shire-studio/laravel-starter-kit-vue.git)) at **v1.3.0**')
        ->toContain('composer transform-types')
        ->toContain('pnpm install')
        ->toContain('`.starter-kit-update/REMOVED.md`: files that shipped with v1.3.0')
        ->toContain('Never restore a file listed in `.starter-kit-update/REMOVED.md`.')
        ->toContain('`.starter-kit-update/removed.json`')
        ->not->toContain('COMMITS.md')
        ->not->toContain('earlier updates')
        ->and($this->workingDirectory)->not->toBeDirectory();

    Process::assertRan(fn (PendingProcess $process): bool => in_array('create-project', $process->command, true)
        && $process->command[3] === "{$this->workingDirectory}/old"
        && $process->command[4] === 'v1.3.0');
});

it('explains how to finish the update by hand', function (): void {
    fakeStarterKitDownloads();

    resolve(PrepareStarterKitUpdate::class)->handle(new StarterKitManifest(starterKitRelease('v1.3.0', 'a1b2c3')), starterKitRelease(), UpdateDirectory::in($this->directory));

    expect(File::get("{$this->updateDirectory}/PROMPT.md"))
        ->toContain('If the starter kit CLI started this session, stop here and tell the user to exit it.')
        ->toContain('Copy `.starter-kit-update/.starter-kit.json` to `.starter-kit.json` in the project root. If `.starter-kit-update/removed.json` exists, add each feature in it')
        ->toContain('Delete the `.starter-kit-update/` directory.');
});

it('includes the upstream commit log when it can be fetched', function (): void {
    fakeStarterKitDownloads([
        "*'clone'*" => Process::result(),
        "*'log'*" => Process::result("## feat: add two factor authentication\n"),
    ]);

    resolve(PrepareStarterKitUpdate::class)->handle(new StarterKitManifest(starterKitRelease('v1.3.0', 'a1b2c3')), starterKitRelease(), UpdateDirectory::in($this->directory));

    expect(File::get("{$this->updateDirectory}/COMMITS.md"))->toBe("## feat: add two factor authentication\n")
        ->and(File::get("{$this->updateDirectory}/PROMPT.md"))->toContain('`.starter-kit-update/COMMITS.md`: the upstream commit messages');
});

it('carries over the features the project removed in earlier updates', function (): void {
    fakeStarterKitDownloads();

    resolve(PrepareStarterKitUpdate::class)->handle(new StarterKitManifest(starterKitRelease('v1.3.0', 'a1b2c3'), ['Teams', 'Billing & invoices']), starterKitRelease(), UpdateDirectory::in($this->directory));

    expect(File::get("{$this->updateDirectory}/PROMPT.md"))->toContain("removed from this project on purpose in earlier updates:\n\n- Teams\n- Billing & invoices\n")
        ->and(File::json("{$this->updateDirectory}/.starter-kit.json"))->toMatchArray(['removed' => ['Teams', 'Billing & invoices']]);
});

it('leaves out the removed files list when the project has every starter kit file', function (): void {
    fakeStarterKitDownloads();

    createFiles($this->directory, [
        'app/Http/Controllers/Auth/LoginController.php' => "<?php // login\n",
        'config/legacy.php' => "<?php // legacy\n",
    ]);

    $update = resolve(PrepareStarterKitUpdate::class)->handle(new StarterKitManifest(starterKitRelease('v1.3.0', 'a1b2c3')), starterKitRelease(), UpdateDirectory::in($this->directory));

    expect($update->removedFiles)->toBeEmpty()
        ->and("{$this->updateDirectory}/REMOVED.md")->not->toBeFile()
        ->and(File::get("{$this->updateDirectory}/PROMPT.md"))->not->toContain('REMOVED.md`: files that shipped');
});

it('groups removed files in the project root under their own heading', function (): void {
    fakeStarterKitDownloads();

    File::delete("{$this->directory}/composer.json");

    resolve(PrepareStarterKitUpdate::class)->handle(new StarterKitManifest(starterKitRelease('v1.3.0', 'a1b2c3')), starterKitRelease(), UpdateDirectory::in($this->directory));

    expect(File::get("{$this->updateDirectory}/REMOVED.md"))->toContain("## Project root\n\n- composer.json\n");
});

it('replaces a stale update directory', function (): void {
    fakeStarterKitDownloads();

    createFiles($this->updateDirectory, ['removed.json' => '["Teams"]']);

    resolve(PrepareStarterKitUpdate::class)->handle(new StarterKitManifest(starterKitRelease('v1.3.0', 'a1b2c3')), starterKitRelease(), UpdateDirectory::in($this->directory));

    expect("{$this->updateDirectory}/removed.json")->not->toBeFile();
});

it('cleans up the downloaded releases when a download fails', function (): void {
    fakeStarterKitDownloads([
        "*'create-project'*" => function (PendingProcess $process): FakeProcessResult {
            createFiles($process->command[3], ['composer.json' => '{}']);

            return Process::result(exitCode: 1);
        },
    ]);

    expect(fn () => resolve(PrepareStarterKitUpdate::class)->handle(new StarterKitManifest(starterKitRelease('v1.3.0', 'a1b2c3')), starterKitRelease(), UpdateDirectory::in($this->directory)))
        ->toThrow(ProcessFailedException::class)
        ->and($this->workingDirectory)->not->toBeDirectory()
        ->and($this->updateDirectory)->not->toBeDirectory();
});
