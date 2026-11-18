<?php

declare(strict_types=1);

use App\Actions\RecordStarterKitRelease;
use App\Data\StarterKitManifest;
use App\Enums\Kit;
use Illuminate\Process\FakeProcessResult;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

beforeEach(function (): void {
    $this->originalWorkingDirectory = getcwd();
    $this->projectDirectory = temporaryDirectory();
    $this->updateDirectory = "{$this->projectDirectory}/.starter-kit-update";

    createFiles($this->projectDirectory, [
        'composer.json' => "{\"version\": 1}\n",
        'app/Models/User.php' => "<?php // base user\n",
        'routes/web.php' => "<?php // project routes\n",
    ]);

    resolve(RecordStarterKitRelease::class)->handle(new StarterKitManifest(starterKitRelease()), $this->projectDirectory);

    chdir($this->projectDirectory);
});

afterEach(function (): void {
    chdir($this->originalWorkingDirectory);

    File::deleteDirectory($this->projectDirectory);
});

function fakeTeamsProcesses(string $projectDirectory, array $overrides = []): void
{
    Process::fake($overrides + [
        "*'rev-parse' '--is-inside-work-tree'" => Process::result("true\n"),
        "*'--git-path'*" => Process::result("{$projectDirectory}/.git/info/exclude\n"),
        "*'status'*" => Process::result(),
        "*'show'*" => Process::result(composerShowOutput(['v1.4.0', 'v1.3.0'])),
        "*'create-project'*" => function (PendingProcess $process): FakeProcessResult {
            createFiles($process->command[3], [
                'composer.json' => "{\"version\": 1}\n",
                'app/Models/User.php' => "<?php // base user\n",
                'routes/web.php' => "<?php // base routes\n",
            ]);

            createTeamsOverlay($process->command[3], [
                'app/Models/Team.php' => "<?php // team\n",
                'app/Models/User.php' => "<?php // team user\n",
                'routes/web.php' => "<?php // team routes\n",
            ]);

            return Process::result();
        },
        "*'write-tree'" => Process::result("4b825dc642cb6eb9a060e54bf8d69288fbee4904\n"),
        "*'--name-status'*" => Process::result("A\0app/Models/Team.php\0M\0app/Models/User.php\0M\0routes/web.php\0"),
        "*'diff'*" => Process::result("diff --git a/routes/web.php b/routes/web.php\n"),
        "*'merge-file'*" => Process::result(exitCode: 1),
        '*' => Process::result(),
    ]);
}

function fakeTeamsAgentSession(string $projectDirectory, int $exitCode = 0): Closure
{
    return function () use ($projectDirectory, $exitCode): FakeProcessResult {
        File::put("{$projectDirectory}/routes/web.php", "<?php // project and team routes\n");

        return Process::result(exitCode: $exitCode);
    };
}

it('adds teams when every change applies cleanly', function (): void {
    fakeTeamsProcesses($this->projectDirectory);

    File::put("{$this->projectDirectory}/routes/web.php", "<?php // base routes\n");

    $this->artisan('teams')
        ->expectsOutputToContain('Applied the team changes to 3 files.')
        ->expectsOutputToContain('composer transform-types')
        ->expectsOutputToContain('The project has teams.')
        ->assertSuccessful();

    expect(File::json("{$this->projectDirectory}/.starter-kit.json"))->toMatchArray(['version' => 'v1.4.0', 'teams' => true])
        ->and(File::get("{$this->projectDirectory}/app/Models/Team.php"))->toBe("<?php // team\n")
        ->and(File::get("{$this->projectDirectory}/routes/web.php"))->toBe("<?php // team routes\n")
        ->and($this->updateDirectory)->not->toBeDirectory()
        ->and(File::get("{$this->projectDirectory}/.git/info/exclude"))->toBe("/.starter-kit-update/\n");

    Process::assertDidntRun(fn (PendingProcess $process): bool => $process->command[0] === 'claude' && $process->command[1] !== '--version');
});

it('hands the remaining merges to the chosen agent', function (): void {
    fakeTeamsProcesses($this->projectDirectory, [
        "'claude' 'Read*" => fakeTeamsAgentSession($this->projectDirectory),
        "*'status'*" => Process::sequence()
            ->push(Process::result())
            ->push(Process::result(" M routes/web.php\n")),
    ]);

    $this->artisan('teams')
        ->expectsOutputToContain('Applied the team changes to 2 files.')
        ->expectsOutputToContain('1 files this project changed need the team changes merged by hand:')
        ->expectsChoice('Which agent should finish adding teams?', 'claude', [
            'claude' => 'Claude Code',
            'codex' => 'Codex',
            'gemini' => 'Gemini CLI',
            'opencode' => 'OpenCode',
            'cursor' => 'Cursor',
            'none' => 'None, just give me the files',
        ])
        ->expectsOutputToContain(' M routes/web.php')
        ->expectsConfirmation('Mark the project as having teams?', 'yes')
        ->expectsOutputToContain('The project has teams.')
        ->assertSuccessful();

    expect(File::json("{$this->projectDirectory}/.starter-kit.json"))->toMatchArray(['teams' => true])
        ->and($this->updateDirectory)->not->toBeDirectory();

    Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['claude', 'Read .starter-kit-update/PROMPT.md and follow the instructions in it. The starter kit CLI started this session and carries on once you exit.']
        && $process->path === $this->projectDirectory);
});

it('keeps the project without teams when it is not marked as done', function (): void {
    fakeTeamsProcesses($this->projectDirectory, [
        "'claude' 'Read*" => fakeTeamsAgentSession($this->projectDirectory, exitCode: 1),
    ]);

    $this->artisan('teams', ['--agent' => 'claude'])
        ->expectsOutputToContain('Claude Code exited with code 1.')
        ->expectsOutputToContain('No files were changed.')
        ->expectsConfirmation('Mark the project as having teams?', 'no')
        ->expectsOutputToContain('The project is still marked as having no teams. To finish by hand, copy [.starter-kit-update/.starter-kit.json] to [.starter-kit.json] and delete [.starter-kit-update].')
        ->assertSuccessful();

    expect(File::json("{$this->projectDirectory}/.starter-kit.json"))->toMatchArray(['teams' => false])
        ->and(File::json("{$this->updateDirectory}/.starter-kit.json"))->toMatchArray(['teams' => true]);
});

it('prepares the remaining merges without an agent when running without interaction', function (): void {
    fakeTeamsProcesses($this->projectDirectory);

    $this->artisan('teams', ['--no-interaction' => true])
        ->expectsOutputToContain('Follow .starter-kit-update/PROMPT.md yourself, or hand it to any coding agent.')
        ->expectsOutputToContain('The rest of the team changes are ready to finish.')
        ->assertSuccessful();

    expect(File::get("{$this->updateDirectory}/PROMPT.md"))->toContain('- `routes/web.php`')
        ->and(File::json("{$this->projectDirectory}/.starter-kit.json"))->toMatchArray(['teams' => false])
        ->and(File::get("{$this->projectDirectory}/app/Models/Team.php"))->toBe("<?php // team\n");
});

it('leaves out the frontend steps for a kit without a frontend', function (): void {
    resolve(RecordStarterKitRelease::class)->handle(new StarterKitManifest(starterKitRelease(kit: Kit::Api)), $this->projectDirectory);

    fakeTeamsProcesses($this->projectDirectory);

    File::put("{$this->projectDirectory}/routes/web.php", "<?php // base routes\n");

    $this->artisan('teams')
        ->doesntExpectOutputToContain('pnpm')
        ->doesntExpectOutputToContain('transform-types')
        ->assertSuccessful();
});

it('does nothing when the project already has teams', function (): void {
    resolve(RecordStarterKitRelease::class)->handle(new StarterKitManifest(starterKitRelease(), teams: true), $this->projectDirectory);

    fakeTeamsProcesses($this->projectDirectory);

    $this->artisan('teams')
        ->expectsOutputToContain('The project already has teams.')
        ->assertSuccessful();

    Process::assertDidntRun(fn (PendingProcess $process): bool => in_array('create-project', (array) $process->command, true));
});

it('fails for a kit without teams', function (): void {
    resolve(RecordStarterKitRelease::class)->handle(new StarterKitManifest(starterKitRelease(kit: Kit::Blade)), $this->projectDirectory);

    fakeTeamsProcesses($this->projectDirectory);

    $this->artisan('teams')
        ->expectsOutputToContain('The Blade starter kit has no teams variant.')
        ->assertFailed();
});

it('asks before adding teams when the project removed something about authentication', function (): void {
    resolve(RecordStarterKitRelease::class)->handle(new StarterKitManifest(starterKitRelease(), ['Billing', 'Two factor authentication']), $this->projectDirectory);

    fakeTeamsProcesses($this->projectDirectory);

    File::put("{$this->projectDirectory}/routes/web.php", "<?php // base routes\n");

    $this->artisan('teams', ['--agent' => 'none'])
        ->expectsOutputToContain('[.starter-kit.json] records removed features that mention login, registration or authentication: Two factor authentication. Teams need login and registration.')
        ->expectsConfirmation('Add teams anyway?', 'yes')
        ->expectsOutputToContain('The rest of the team changes are ready to finish.')
        ->assertSuccessful();

    expect(File::json("{$this->updateDirectory}/.starter-kit.json"))->toMatchArray([
        'teams' => true,
        'removed' => ['Billing', 'Two factor authentication'],
    ]);
});

it('does not add teams when the user stops at the authentication warning', function (): void {
    resolve(RecordStarterKitRelease::class)->handle(new StarterKitManifest(starterKitRelease(), ['Authentication (login, registration and two factor)']), $this->projectDirectory);

    fakeTeamsProcesses($this->projectDirectory);

    $this->artisan('teams')
        ->expectsConfirmation('Add teams anyway?', 'no')
        ->expectsOutputToContain('Teams were not added.')
        ->assertFailed();

    Process::assertDidntRun(fn (PendingProcess $process): bool => in_array('create-project', (array) $process->command, true));
});

it('does not add teams without interaction when the project removed something about authentication', function (): void {
    resolve(RecordStarterKitRelease::class)->handle(new StarterKitManifest(starterKitRelease(), ['Authentication']), $this->projectDirectory);

    fakeTeamsProcesses($this->projectDirectory);

    $this->artisan('teams', ['--no-interaction' => true])
        ->expectsOutputToContain('Teams were not added.')
        ->assertFailed();

    Process::assertDidntRun(fn (PendingProcess $process): bool => in_array('create-project', (array) $process->command, true));
});

it('fails without a manifest', function (): void {
    File::delete("{$this->projectDirectory}/.starter-kit.json");

    fakeTeamsProcesses($this->projectDirectory);

    $this->artisan('teams')
        ->expectsOutputToContain('No [.starter-kit.json] was found, so the starter kit release this project was created from is unknown. Run update first to record it.')
        ->assertFailed();
});

it('fails when the manifest cannot be read', function (): void {
    File::put("{$this->projectDirectory}/.starter-kit.json", 'v1.4.0');

    fakeTeamsProcesses($this->projectDirectory);

    $this->artisan('teams')
        ->expectsOutputToContain('The [.starter-kit.json] file could not be read.')
        ->assertFailed();
});

it('fails when the recorded version is not a stable release', function (): void {
    resolve(RecordStarterKitRelease::class)->handle(new StarterKitManifest(starterKitRelease('v1.5.0-beta.1')), $this->projectDirectory);

    fakeTeamsProcesses($this->projectDirectory);

    $this->artisan('teams')
        ->expectsOutputToContain('Starter kit v1.5.0-beta.1 recorded in [.starter-kit.json] is not a stable release.')
        ->assertFailed();
});

it('fails when the starter kit releases cannot be listed', function (): void {
    fakeTeamsProcesses($this->projectDirectory, [
        "*'show'*" => Process::result('not json'),
    ]);

    $this->artisan('teams')
        ->expectsOutputToContain('Composer returned metadata for [shire-studio/laravel-starter-kit-vue] that could not be read.')
        ->assertFailed();
});

it('fails when the recorded release has no teams variant', function (): void {
    fakeTeamsProcesses($this->projectDirectory, [
        "*'create-project'*" => function (PendingProcess $process): FakeProcessResult {
            createFiles($process->command[3], ['composer.json' => '{}']);

            return Process::result();
        },
    ]);

    $this->artisan('teams')
        ->expectsOutputToContain('The Vue starter kit v1.4.0 has no teams variant. Run update first to move the project to a release with teams.')
        ->assertFailed();

    expect(File::get("{$this->projectDirectory}/app/Models/User.php"))->toBe("<?php // base user\n");
});

it('fails when a subprocess fails', function (): void {
    fakeTeamsProcesses($this->projectDirectory, [
        "*'create-project'*" => Process::result(output: 'Download output', errorOutput: 'Download failed', exitCode: 1),
    ]);

    $this->artisan('teams')
        ->expectsOutputToContain('Download failed')
        ->expectsOutputToContain('Download output')
        ->expectsOutputToContain('failed with exit code 1.')
        ->assertFailed();
});

it('fails when the staged manifest disappears', function (): void {
    fakeTeamsProcesses($this->projectDirectory, [
        "'claude' 'Read*" => function (): FakeProcessResult {
            File::delete("{$this->updateDirectory}/.starter-kit.json");

            return Process::result();
        },
    ]);

    $this->artisan('teams', ['--agent' => 'claude'])
        ->expectsConfirmation('Mark the project as having teams?', 'yes')
        ->expectsOutputToContain('The staged [.starter-kit-update/.starter-kit.json] could not be found')
        ->assertFailed();
});

it('rejects an unknown agent', function (): void {
    Process::fake();

    $this->artisan('teams', ['--agent' => 'copilot'])
        ->expectsOutputToContain('The agent must be one of: claude, codex, gemini, opencode, cursor, none.')
        ->assertFailed();

    Process::assertNothingRan();
});

it('rejects an agent without an interactive terminal', function (): void {
    Process::fake();

    $this->artisan('teams', ['--agent' => 'claude', '--no-interaction' => true])
        ->expectsOutputToContain('An agent session needs an interactive terminal. Run teams without --no-interaction, or use --agent=none.')
        ->assertFailed();

    Process::assertNothingRan();
});

it('fails when the chosen agent is not installed', function (): void {
    fakeTeamsProcesses($this->projectDirectory, [
        "'codex' '--version'" => Process::result(exitCode: 127),
    ]);

    $this->artisan('teams', ['--agent' => 'codex'])
        ->expectsOutputToContain('Codex could not be found. Install it or choose another agent.')
        ->assertFailed();

    expect("{$this->projectDirectory}/app/Models/Team.php")->not->toBeFile();
});

it('fails when required tools are missing', function (): void {
    fakeTeamsProcesses($this->projectDirectory, [
        "'git' '--version'" => Process::result(exitCode: 127),
    ]);

    $this->artisan('teams')
        ->expectsOutputToContain('The following required tools could not be found: git.')
        ->assertFailed();
});

it('fails outside a composer project', function (): void {
    File::delete("{$this->projectDirectory}/composer.json");

    fakeTeamsProcesses($this->projectDirectory);

    $this->artisan('teams')
        ->expectsOutputToContain("No composer.json was found in [{$this->projectDirectory}]. Run teams from the root of a project created from the starter kit.")
        ->assertFailed();
});

it('fails outside a git repository', function (): void {
    fakeTeamsProcesses($this->projectDirectory, [
        "*'rev-parse' '--is-inside-work-tree'" => Process::result(exitCode: 128),
    ]);

    $this->artisan('teams')
        ->expectsOutputToContain('is not a git repository. Commit the project to git first so adding teams can be reviewed and undone.')
        ->assertFailed();
});

it('fails when the project has uncommitted changes', function (): void {
    fakeTeamsProcesses($this->projectDirectory, [
        "*'status'*" => Process::result(" M routes/web.php\n"),
    ]);

    $this->artisan('teams')
        ->expectsOutputToContain('The project has uncommitted changes. Commit or stash them first so adding teams can be reviewed and undone, or use --force.')
        ->assertFailed();
});

it('runs with uncommitted changes when forced', function (): void {
    fakeTeamsProcesses($this->projectDirectory, [
        "*'status'*" => Process::result(" M routes/web.php\n"),
    ]);

    $this->artisan('teams', ['--force' => true, '--agent' => 'none'])
        ->expectsOutputToContain('The rest of the team changes are ready to finish.')
        ->assertSuccessful();
});

it('fails without suggesting an update when the teams variant cannot be applied', function (): void {
    fakeTeamsProcesses($this->projectDirectory, [
        "*'create-project'*" => function (PendingProcess $process): FakeProcessResult {
            createFiles($process->command[3], ['composer.json' => '{}']);

            createTeamsOverlay($process->command[3], version: 2);

            return Process::result();
        },
    ]);

    $this->artisan('teams')
        ->doesntExpectOutputToContain('Run update first')
        ->assertFailed();
});

it('refuses to run while starter kit changes are waiting to be marked as done', function (): void {
    createFiles($this->updateDirectory, ['.starter-kit.json' => '{"version": "v1.5.0"}']);

    fakeTeamsProcesses($this->projectDirectory);

    $this->artisan('teams')
        ->expectsOutputToContain('[.starter-kit-update] holds starter kit changes that were not marked as done. Finish them by copying [.starter-kit-update/.starter-kit.json] to [.starter-kit.json] and deleting [.starter-kit-update], or delete [.starter-kit-update] to discard them. Then run teams again.')
        ->assertFailed();

    expect(File::get("{$this->updateDirectory}/.starter-kit.json"))->toBe('{"version": "v1.5.0"}');

    Process::assertDidntRun(fn (PendingProcess $process): bool => in_array('create-project', (array) $process->command, true));
});

it('replaces an update directory that holds no staged changes', function (): void {
    createFiles($this->updateDirectory, ['changes.diff' => 'stale']);

    fakeTeamsProcesses($this->projectDirectory);

    $this->artisan('teams', ['--agent' => 'none'])
        ->expectsOutputToContain('The rest of the team changes are ready to finish.')
        ->assertSuccessful();

    expect(File::get("{$this->updateDirectory}/changes.diff"))->not->toBe('stale');
});

it('asks for the new team files to be checked when the project removed features', function (): void {
    resolve(RecordStarterKitRelease::class)->handle(new StarterKitManifest(starterKitRelease(), ['Billing']), $this->projectDirectory);

    fakeTeamsProcesses($this->projectDirectory);

    File::put("{$this->projectDirectory}/routes/web.php", "<?php // base routes\n");

    $this->artisan('teams', ['--no-interaction' => true])
        ->expectsOutputToContain('Applied the team changes to 3 files.')
        ->expectsOutputToContain('1 new team files need checking against the features this project removed.')
        ->expectsOutputToContain('The rest of the team changes are ready to finish.')
        ->assertSuccessful();

    expect(File::get("{$this->projectDirectory}/app/Models/Team.php"))->toBe("<?php // team\n")
        ->and(File::get("{$this->updateDirectory}/PROMPT.md"))->toContain("## Check new files against removed features\n\nThe CLI added these new team files to the project:\n\n- `app/Models/Team.php`\n")
        ->not->toContain('## Files to merge')
        ->and(File::json("{$this->projectDirectory}/.starter-kit.json"))->toMatchArray(['teams' => false]);
});

it('warns when the project removed login or registration', function (): void {
    resolve(RecordStarterKitRelease::class)->handle(new StarterKitManifest(starterKitRelease(), ['Billing', 'Registration', 'Social Login']), $this->projectDirectory);

    fakeTeamsProcesses($this->projectDirectory);

    $this->artisan('teams')
        ->expectsOutputToContain('mention login, registration or authentication: Registration, Social Login.')
        ->expectsConfirmation('Add teams anyway?', 'no')
        ->assertFailed();
});

it('explains how to undo the changes when adding teams stops partway', function (): void {
    fakeTeamsProcesses($this->projectDirectory);

    File::put("{$this->projectDirectory}/routes/web.php", "<?php // base routes\n");

    chmod("{$this->projectDirectory}/routes/web.php", 0o444);

    $this->artisan('teams')
        ->expectsOutputToContain('See them with git status, and undo them with git restore and git clean if you want to start again.')
        ->assertFailed();

    expect(File::json("{$this->projectDirectory}/.starter-kit.json"))->toMatchArray(['teams' => false]);
});
