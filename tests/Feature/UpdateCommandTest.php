<?php

declare(strict_types=1);

use App\Actions\RecordStarterKitRelease;
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
        'routes/web.php' => "<?php // version 1\n",
    ]);

    resolve(RecordStarterKitRelease::class)->handle(starterKitRelease('v1.3.0', 'a1b2c3'), $this->projectDirectory);

    chdir($this->projectDirectory);
});

afterEach(function (): void {
    chdir($this->originalWorkingDirectory);

    File::deleteDirectory($this->projectDirectory);
});

function fakeUpdateProcesses(string $projectDirectory, array $overrides = []): void
{
    Process::fake($overrides + [
        "*'rev-parse' '--is-inside-work-tree'" => Process::result("true\n"),
        "*'--git-path'*" => Process::result("{$projectDirectory}/.git/info/exclude\n"),
        "*'status'*" => Process::result(),
        "*'show'*'v1.3.0'*" => Process::result(composerShowOutput(['v1.3.0'], 'a1b2c3')),
        "*'show'*'v1.4.0'*" => Process::result(composerShowOutput(['v1.4.0'])),
        "*'show'*" => Process::result(composerShowOutput(['v1.4.0', 'v1.3.0'])),
        "*'create-project'*" => function (PendingProcess $process): FakeProcessResult {
            createFiles($process->command[3], [
                'composer.json' => "{\"version\": 1}\n",
                'routes/web.php' => $process->command[4] === 'v1.3.0' ? "<?php // version 1\n" : "<?php // version 2\n",
                'app/Http/Controllers/Auth/LoginController.php' => "<?php // login\n",
            ]);

            return Process::result();
        },
        "*'write-tree'" => Process::result("4b825dc642cb6eb9a060e54bf8d69288fbee4904\n"),
        "*'--name-status'*" => Process::result("M\0routes/web.php\0"),
        "*'diff'*" => Process::result("diff --git a/routes/web.php b/routes/web.php\n"),
        "*'clone'*" => Process::result(exitCode: 128),
        '*' => Process::result(),
    ]);
}

function fakeAgentSession(string $projectDirectory, string $proposedRemovedFeatures = '[]', int $exitCode = 0): Closure
{
    return function () use ($projectDirectory, $proposedRemovedFeatures, $exitCode): FakeProcessResult {
        File::put("{$projectDirectory}/routes/web.php", "<?php // version 2\n");
        File::put("{$projectDirectory}/.starter-kit-update/removed.json", $proposedRemovedFeatures);

        return Process::result(exitCode: $exitCode);
    };
}

it('updates the project with the chosen agent', function (): void {
    fakeUpdateProcesses($this->projectDirectory, [
        "'claude' 'Read*" => fakeAgentSession($this->projectDirectory, '["Authentication"]'),
        "*'status'*" => Process::sequence()
            ->push(Process::result())
            ->push(Process::result(" M routes/web.php\n")),
    ]);

    $this->artisan('update')
        ->expectsOutputToContain('Prepared 1 changed files from starter kit v1.3.0 to v1.4.0 in [.starter-kit-update].')
        ->expectsOutputToContain('Found 1 starter kit files this project has removed. They will stay removed.')
        ->expectsChoice('Which agent should apply the update?', 'claude', [
            'claude' => 'Claude Code',
            'codex' => 'Codex',
            'gemini' => 'Gemini CLI',
            'opencode' => 'OpenCode',
            'cursor' => 'Cursor',
            'none' => 'None, just give me the files',
        ])
        ->expectsOutputToContain(' M routes/web.php')
        ->expectsChoice('Which removed features should be recorded so future updates leave them out?', ['Authentication'], ['Authentication'])
        ->expectsConfirmation('Mark the project as updated to starter kit v1.4.0?', 'yes')
        ->expectsOutputToContain('git add --all && git commit --message="chore: update starter kit from v1.3.0 to v1.4.0"')
        ->expectsOutputToContain('The project is updated to starter kit v1.4.0.')
        ->assertSuccessful();

    expect(File::json("{$this->projectDirectory}/.starter-kit.json"))->toMatchArray([
        'version' => 'v1.4.0',
        'reference' => 'c31ae68197d092fbaac6ac68712b44f230e27850',
        'removed' => ['Authentication'],
    ])
        ->and($this->updateDirectory)->not->toBeDirectory()
        ->and(File::get("{$this->projectDirectory}/.git/info/exclude"))->toBe("/.starter-kit-update/\n");

    Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['claude', 'Read .starter-kit-update/PROMPT.md and follow the instructions in it. The starter kit CLI started this session and carries on once you exit.']
        && $process->path === $this->projectDirectory);
});

it('only offers the agents that are installed', function (): void {
    fakeUpdateProcesses($this->projectDirectory, [
        "'codex' '--version'" => Process::result(exitCode: 127),
        "'gemini' '--version'" => Process::result(exitCode: 127),
    ]);

    $this->artisan('update')
        ->expectsChoice('Which agent should apply the update?', 'none', [
            'claude' => 'Claude Code',
            'opencode' => 'OpenCode',
            'cursor' => 'Cursor',
            'none' => 'None, just give me the files',
        ])
        ->expectsOutputToContain('The update is ready to apply.')
        ->assertSuccessful();
});

it('keeps the recorded removed features when more are proposed', function (): void {
    resolve(RecordStarterKitRelease::class)->handle(starterKitRelease('v1.3.0', 'a1b2c3'), $this->projectDirectory, ['Teams']);

    fakeUpdateProcesses($this->projectDirectory, [
        "'claude' 'Read*" => fakeAgentSession($this->projectDirectory, '["Teams", "Authentication"]'),
    ]);

    $this->artisan('update', ['--agent' => 'claude'])
        ->expectsChoice('Which removed features should be recorded so future updates leave them out?', ['Authentication'], ['Authentication'])
        ->expectsConfirmation('Mark the project as updated to starter kit v1.4.0?', 'yes')
        ->assertSuccessful();

    expect(File::json("{$this->projectDirectory}/.starter-kit.json")['removed'])->toBe(['Teams', 'Authentication']);
});

it('only records the removed features that are accepted', function (): void {
    fakeUpdateProcesses($this->projectDirectory, [
        "'claude' 'Read*" => fakeAgentSession($this->projectDirectory, '["Authentication", "Billing"]'),
    ]);

    $this->artisan('update', ['--agent' => 'claude'])
        ->expectsChoice('Which removed features should be recorded so future updates leave them out?', ['Billing'], ['Authentication', 'Billing'])
        ->expectsConfirmation('Mark the project as updated to starter kit v1.4.0?', 'yes')
        ->assertSuccessful();

    expect(File::json("{$this->projectDirectory}/.starter-kit.json")['removed'])->toBe(['Billing']);
});

it('stages the accepted removed features when the update is not marked as done', function (): void {
    fakeUpdateProcesses($this->projectDirectory, [
        "'claude' 'Read*" => fakeAgentSession($this->projectDirectory, '["Authentication"]'),
    ]);

    $this->artisan('update', ['--agent' => 'claude'])
        ->expectsChoice('Which removed features should be recorded so future updates leave them out?', ['Authentication'], ['Authentication'])
        ->expectsConfirmation('Mark the project as updated to starter kit v1.4.0?', 'no')
        ->assertSuccessful();

    expect(File::json("{$this->updateDirectory}/.starter-kit.json"))->toMatchArray([
        'version' => 'v1.4.0',
        'removed' => ['Authentication'],
    ])
        ->and("{$this->updateDirectory}/removed.json")->not->toBeFile()
        ->and(File::json("{$this->projectDirectory}/.starter-kit.json"))->not->toHaveKey('removed');
});

it('prepares the files without an agent when running without interaction', function (): void {
    fakeUpdateProcesses($this->projectDirectory);

    $this->artisan('update', ['--no-interaction' => true])
        ->expectsOutputToContain('REMOVED.md      starter kit files this project removed')
        ->expectsOutputToContain('The update is ready to apply.')
        ->assertSuccessful();

    expect(File::get("{$this->updateDirectory}/PROMPT.md"))->toContain('Copy `.starter-kit-update/.starter-kit.json` to `.starter-kit.json` in the project root.')
        ->and(File::json("{$this->projectDirectory}/.starter-kit.json")['version'])->toBe('v1.3.0');

    Process::assertDidntRun(fn (PendingProcess $process): bool => $process->command[0] === 'claude' && $process->command[1] !== '--version');
});

it('keeps the current version when the update is not marked as done', function (): void {
    fakeUpdateProcesses($this->projectDirectory, [
        "'claude' 'Read*" => fakeAgentSession($this->projectDirectory),
    ]);

    $this->artisan('update', ['--agent' => 'claude'])
        ->expectsOutputToContain('No files were changed.')
        ->expectsConfirmation('Mark the project as updated to starter kit v1.4.0?', 'no')
        ->expectsOutputToContain('The project is still marked as starter kit v1.3.0. To finish by hand, copy [.starter-kit-update/.starter-kit.json] to [.starter-kit.json] and delete [.starter-kit-update].')
        ->assertSuccessful();

    expect(File::json("{$this->projectDirectory}/.starter-kit.json")['version'])->toBe('v1.3.0')
        ->and("{$this->updateDirectory}/PROMPT.md")->toBeFile();
});

it('warns when the agent exits with an error', function (): void {
    fakeUpdateProcesses($this->projectDirectory, [
        "'claude' 'Read*" => fakeAgentSession($this->projectDirectory, exitCode: 1),
    ]);

    $this->artisan('update', ['--agent' => 'claude'])
        ->expectsOutputToContain('Claude Code exited with code 1.')
        ->expectsConfirmation('Mark the project as updated to starter kit v1.4.0?', 'no')
        ->assertSuccessful();
});

it('marks the project as updated when the release has no changes for it', function (): void {
    fakeUpdateProcesses($this->projectDirectory, [
        "*'--name-status'*" => Process::result(),
        "*'diff'*" => Process::result(),
    ]);

    $this->artisan('update', ['--agent' => 'claude'])
        ->expectsOutputToContain('Starter kit v1.4.0 has no changes for this project, so it has been marked as updated.')
        ->assertSuccessful();

    expect(File::json("{$this->projectDirectory}/.starter-kit.json")['version'])->toBe('v1.4.0')
        ->and($this->updateDirectory)->not->toBeDirectory();
});

it('marks the project as updated without interaction when the release has no changes for it', function (): void {
    fakeUpdateProcesses($this->projectDirectory, [
        "*'--name-status'*" => Process::result(),
        "*'diff'*" => Process::result(),
    ]);

    $this->artisan('update', ['--no-interaction' => true])
        ->expectsOutputToContain('Starter kit v1.4.0 has no changes for this project, so it has been marked as updated.')
        ->assertSuccessful();

    expect(File::json("{$this->projectDirectory}/.starter-kit.json")['version'])->toBe('v1.4.0');
});

it('fails when the recorded version is not a stable release', function (): void {
    resolve(RecordStarterKitRelease::class)->handle(starterKitRelease('v1.5.0-beta.1'), $this->projectDirectory);

    fakeUpdateProcesses($this->projectDirectory);

    $this->artisan('update')
        ->expectsOutputToContain('Starter kit v1.5.0-beta.1 recorded in [.starter-kit.json] is not a stable release. Set it to the stable release this project was created from, then run update again.')
        ->assertFailed();

    Process::assertDidntRun(fn (PendingProcess $process): bool => in_array('create-project', $process->command, true));
});

it('does nothing when the project is already up to date', function (): void {
    resolve(RecordStarterKitRelease::class)->handle(starterKitRelease(), $this->projectDirectory);

    fakeUpdateProcesses($this->projectDirectory);

    $this->artisan('update')
        ->expectsOutputToContain('The project is already up to date with starter kit v1.4.0.')
        ->assertSuccessful();

    Process::assertRanTimes(fn (PendingProcess $process): bool => $process->command === ['composer', 'show', 'shire-studio/laravel-starter-kit-vue', '--all', '--format=json'], 1);
    Process::assertDidntRun(fn (PendingProcess $process): bool => in_array('create-project', $process->command, true));
});

it('detects the starter kit version when the project has no manifest', function (): void {
    File::delete("{$this->projectDirectory}/.starter-kit.json");

    fakeUpdateProcesses($this->projectDirectory, [
        "'claude' 'Read .starter-kit-update/DETECT.md*" => function (): FakeProcessResult {
            File::put("{$this->updateDirectory}/detected-version", "v1.3.0\n");

            return Process::result();
        },
    ]);

    $this->artisan('update', ['--kit' => 'vue', '--agent' => 'claude'])
        ->expectsOutputToContain('No [.starter-kit.json] was found. Claude Code will work out which starter kit version this project was created from.')
        ->expectsConfirmation('Claude Code detected starter kit v1.3.0. Use it as the base for the update?', 'yes')
        ->expectsOutputToContain('Recorded starter kit v1.3.0 in [.starter-kit.json].')
        ->expectsConfirmation('Mark the project as updated to starter kit v1.4.0?', 'no')
        ->assertSuccessful();

    expect(File::json("{$this->projectDirectory}/.starter-kit.json"))->toMatchArray([
        'version' => 'v1.3.0',
        'reference' => 'a1b2c3',
    ]);
});

it('fails when the agent does not detect a starter kit version', function (): void {
    File::delete("{$this->projectDirectory}/.starter-kit.json");

    fakeUpdateProcesses($this->projectDirectory);

    $this->artisan('update', ['--kit' => 'vue', '--agent' => 'claude'])
        ->expectsOutputToContain('Claude Code did not record a starter kit version that exists. Add [.starter-kit.json] by hand or run update again.')
        ->assertFailed();

    expect($this->updateDirectory)->not->toBeDirectory();
});

it('warns when the agent crashes while detecting the version', function (): void {
    File::delete("{$this->projectDirectory}/.starter-kit.json");

    fakeUpdateProcesses($this->projectDirectory, [
        "'claude' 'Read .starter-kit-update/DETECT.md*" => Process::result(errorOutput: 'EINVAL: invalid argument, kqueue', exitCode: 1),
    ]);

    $this->artisan('update', ['--kit' => 'vue', '--agent' => 'claude'])
        ->expectsOutputToContain('Claude Code exited with code 1.')
        ->expectsOutputToContain('Claude Code did not record a starter kit version that exists.')
        ->assertFailed();
});

it('fails when the agent detects a version that does not exist', function (): void {
    File::delete("{$this->projectDirectory}/.starter-kit.json");

    fakeUpdateProcesses($this->projectDirectory, [
        "'claude' 'Read .starter-kit-update/DETECT.md*" => function (): FakeProcessResult {
            File::put("{$this->updateDirectory}/detected-version", 'v0.9.0');

            return Process::result();
        },
    ]);

    $this->artisan('update', ['--kit' => 'vue', '--agent' => 'claude'])
        ->expectsOutputToContain('Claude Code did not record a starter kit version that exists.')
        ->assertFailed();
});

it('fails when the detected version is rejected', function (): void {
    File::delete("{$this->projectDirectory}/.starter-kit.json");

    fakeUpdateProcesses($this->projectDirectory, [
        "'claude' 'Read .starter-kit-update/DETECT.md*" => function (): FakeProcessResult {
            File::put("{$this->updateDirectory}/detected-version", 'v1.3.0');

            return Process::result();
        },
    ]);

    $this->artisan('update', ['--kit' => 'vue', '--agent' => 'claude'])
        ->expectsConfirmation('Claude Code detected starter kit v1.3.0. Use it as the base for the update?', 'no')
        ->expectsOutputToContain('Update cancelled. Add [.starter-kit.json] by hand or run update again.')
        ->assertFailed();

    expect("{$this->projectDirectory}/.starter-kit.json")->not->toBeFile()
        ->and($this->updateDirectory)->not->toBeDirectory();
});

it('fails without a manifest when no agent is chosen', function (): void {
    File::delete("{$this->projectDirectory}/.starter-kit.json");

    fakeUpdateProcesses($this->projectDirectory);

    $this->artisan('update', ['--kit' => 'vue', '--no-interaction' => true])
        ->expectsOutputToContain('No [.starter-kit.json] was found, so the starter kit version this project was created from is unknown. Run update again and choose an agent to detect it, or add [.starter-kit.json] by hand.')
        ->assertFailed();
});

it('fails when the chosen agent is not installed', function (): void {
    fakeUpdateProcesses($this->projectDirectory, [
        "'codex' '--version'" => Process::result(exitCode: 127),
    ]);

    $this->artisan('update', ['--agent' => 'codex'])
        ->expectsOutputToContain('Codex could not be found. Install it or choose another agent.')
        ->assertFailed();
});

it('rejects an agent session when running without interaction', function (): void {
    Process::fake();

    $this->artisan('update', ['--agent' => 'claude', '--no-interaction' => true])
        ->expectsOutputToContain('An agent session needs an interactive terminal. Run update without --no-interaction, or use --agent=none.')
        ->assertFailed();

    Process::assertNothingRan();
});

it('prepares the files when no agent is requested without interaction', function (): void {
    fakeUpdateProcesses($this->projectDirectory);

    $this->artisan('update', ['--agent' => 'none', '--no-interaction' => true])
        ->expectsOutputToContain('The update is ready to apply.')
        ->assertSuccessful();
});

it('rejects an unknown agent', function (): void {
    Process::fake();

    $this->artisan('update', ['--agent' => 'copilot'])
        ->expectsOutputToContain('The agent must be one of: claude, codex, gemini, opencode, cursor, none.')
        ->assertFailed();

    Process::assertNothingRan();
});

it('fails when required tools are missing', function (): void {
    fakeUpdateProcesses($this->projectDirectory, [
        "'git' '--version'" => Process::result(exitCode: 127),
    ]);

    $this->artisan('update')
        ->expectsOutputToContain('The following required tools could not be found: git.')
        ->assertFailed();
});

it('fails outside a project', function (): void {
    File::delete("{$this->projectDirectory}/composer.json");

    fakeUpdateProcesses($this->projectDirectory);

    $this->artisan('update')
        ->expectsOutputToContain("No composer.json was found in [{$this->projectDirectory}]. Run update from the root of a project created from the starter kit.")
        ->assertFailed();
});

it('fails outside a git repository', function (): void {
    fakeUpdateProcesses($this->projectDirectory, [
        "*'rev-parse' '--is-inside-work-tree'" => Process::result(errorOutput: 'fatal: not a git repository', exitCode: 128),
    ]);

    $this->artisan('update')
        ->expectsOutputToContain("[{$this->projectDirectory}] is not a git repository.")
        ->assertFailed();
});

it('fails when the project has uncommitted changes', function (): void {
    fakeUpdateProcesses($this->projectDirectory, [
        "*'status'*" => Process::result(" M routes/web.php\n"),
    ]);

    $this->artisan('update')
        ->expectsOutputToContain('The project has uncommitted changes. Commit or stash them first so the update can be reviewed and undone, or use --force.')
        ->assertFailed();
});

it('runs with uncommitted changes when forced', function (): void {
    fakeUpdateProcesses($this->projectDirectory, [
        "*'status'*" => Process::result(" M routes/web.php\n"),
    ]);

    $this->artisan('update', ['--force' => true, '--no-interaction' => true])
        ->expectsOutputToContain('The update is ready to apply.')
        ->assertSuccessful();
});

it('fails when the manifest cannot be read', function (): void {
    File::put("{$this->projectDirectory}/.starter-kit.json", 'v1.3.0');

    fakeUpdateProcesses($this->projectDirectory);

    $this->artisan('update')
        ->expectsOutputToContain('The [.starter-kit.json] file could not be read. Fix or delete it, then run update again.')
        ->assertFailed();
});

it('fails when a subprocess fails', function (): void {
    fakeUpdateProcesses($this->projectDirectory, [
        "*'create-project'*" => Process::result(output: 'Download output', errorOutput: 'Could not download the release', exitCode: 1),
    ]);

    $this->artisan('update', ['--no-interaction' => true])
        ->expectsOutputToContain('Could not download the release')
        ->expectsOutputToContain('Download output')
        ->expectsOutputToContain('failed with exit code 1.')
        ->assertFailed();
});

it('asks which kit the project was created from when it has no manifest', function (): void {
    File::delete("{$this->projectDirectory}/.starter-kit.json");
    File::put("{$this->projectDirectory}/composer.json", (string) json_encode(['name' => 'shire-studio/laravel-starter-kit-react']));

    fakeUpdateProcesses($this->projectDirectory, [
        "'claude' 'Read .starter-kit-update/DETECT.md*" => function (): FakeProcessResult {
            File::put("{$this->updateDirectory}/detected-version", "v1.3.0\n");

            return Process::result();
        },
    ]);

    $this->artisan('update', ['--agent' => 'claude'])
        ->expectsChoice('Which starter kit was this project created from?', 'react', Kit::options())
        ->expectsConfirmation('Claude Code detected starter kit v1.3.0. Use it as the base for the update?', 'no')
        ->assertFailed();

    Process::assertRan(['composer', 'show', 'shire-studio/laravel-starter-kit-react', '--all', '--format=json']);
});

it('works out the kit from composer.json without interaction', function (): void {
    File::delete("{$this->projectDirectory}/.starter-kit.json");
    File::put("{$this->projectDirectory}/composer.json", (string) json_encode(['name' => 'shire-studio/laravel-starter-kit-livewire']));

    fakeUpdateProcesses($this->projectDirectory);

    $this->artisan('update', ['--no-interaction' => true])
        ->expectsOutputToContain('No [.starter-kit.json] was found, so the starter kit version this project was created from is unknown.')
        ->assertFailed();

    Process::assertRan(['composer', 'show', 'shire-studio/laravel-starter-kit-livewire', '--all', '--format=json']);
});

it('fails without a manifest when the kit cannot be worked out', function (): void {
    File::delete("{$this->projectDirectory}/.starter-kit.json");

    fakeUpdateProcesses($this->projectDirectory);

    $this->artisan('update', ['--no-interaction' => true])
        ->expectsOutputToContain('No [.starter-kit.json] was found, so the starter kit this project was created from is unknown. Pass it with --kit: vue, react, svelte, livewire, api or blade.')
        ->assertFailed();

    Process::assertDidntRun(fn (PendingProcess $process): bool => in_array('show', (array) $process->command, true));
});

it('updates the project from the kit recorded in its manifest', function (): void {
    resolve(RecordStarterKitRelease::class)->handle(starterKitRelease('v1.3.0', 'a1b2c3', Kit::Api), $this->projectDirectory);

    fakeUpdateProcesses($this->projectDirectory);

    $this->artisan('update', ['--no-interaction' => true])
        ->expectsOutputToContain('The update is ready to apply.')
        ->assertSuccessful();

    Process::assertRan(['composer', 'show', 'shire-studio/laravel-starter-kit-api', '--all', '--format=json']);

    expect(File::get("{$this->updateDirectory}/PROMPT.md"))
        ->toContain('API starter kit')
        ->not->toContain('pnpm install')
        ->not->toContain('composer transform-types');
});

it('rejects a kit that does not match the manifest', function (): void {
    Process::fake([
        "*'rev-parse' '--is-inside-work-tree'" => Process::result("true\n"),
        '*' => Process::result(),
    ]);

    $this->artisan('update', ['--kit' => 'react', '--no-interaction' => true])
        ->expectsOutputToContain('[.starter-kit.json] records the Vue starter kit, not React. Run update without --kit.')
        ->assertFailed();
});

it('rejects an unknown kit', function (): void {
    Process::fake();

    $this->artisan('update', ['--kit' => 'angular', '--no-interaction' => true])
        ->expectsOutputToContain('The kit must be one of: vue, react, svelte, livewire, api or blade.')
        ->assertFailed();

    Process::assertNothingRan();
});

it('fails when the manifest names a package that is not a starter kit', function (): void {
    File::put("{$this->projectDirectory}/.starter-kit.json", (string) json_encode([
        'package' => 'shire-studio/laravel-starter-kit',
        'version' => 'v1.3.0',
        'repository' => 'https://github.com/shire-studio/laravel-starter-kit.git',
    ]));

    fakeUpdateProcesses($this->projectDirectory);

    $this->artisan('update', ['--no-interaction' => true])
        ->expectsOutputToContain('The [.starter-kit.json] file names [shire-studio/laravel-starter-kit], which is not a Shire Studio starter kit.')
        ->assertFailed();
});
