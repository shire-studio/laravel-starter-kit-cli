<?php

declare(strict_types=1);

use App\Actions\PrepareTeamsAddition;
use App\Data\StarterKitManifest;
use App\Data\UpdateDirectory;
use App\Enums\Kit;
use App\Exceptions\CouldNotAddTeams;
use App\Exceptions\CouldNotApplyTeamsOverlay;
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
        'app/Models/User.php' => "<?php // base user\n",
        'routes/web.php' => "<?php // project routes\n",
    ]);

    Str::createRandomStringsUsing(fn (): string => basename($this->directory));

    Process::preventStrayProcesses(false);
});

afterEach(function (): void {
    Str::createRandomStringsNormally();

    File::deleteDirectory($this->directory);
});

function fakeTeamsDownloads(bool $withOverlay = true): void
{
    Process::fake([
        "*'create-project'*" => function (PendingProcess $process) use ($withOverlay): FakeProcessResult {
            createFiles($process->command[3], [
                'composer.json' => "{\"version\": 1}\n",
                'app/Models/User.php' => "<?php // base user\n",
                'routes/web.php' => "<?php // base routes\n",
            ]);

            if ($withOverlay) {
                createTeamsOverlay($process->command[3], [
                    'app/Models/Team.php' => "<?php // team\n",
                    'app/Models/User.php' => "<?php // team user\n",
                    'routes/web.php' => "<?php // team routes\n",
                ]);
            }

            return Process::result();
        },
    ]);
}

it('applies the clean teams changes and stages the rest', function (): void {
    fakeTeamsDownloads();

    $addition = resolve(PrepareTeamsAddition::class)->handle(new StarterKitManifest(starterKitRelease(), ['Billing']), UpdateDirectory::in($this->directory));

    expect($addition->appliedFiles)->toBe(['app/Models/Team.php', 'app/Models/User.php'])
        ->and($addition->unresolvedFiles)->toBe(['routes/web.php'])
        ->and($addition->filesToReview)->toBe(['app/Models/Team.php'])
        ->and($addition->isComplete())->toBeFalse()
        ->and(File::get("{$this->directory}/app/Models/Team.php"))->toBe("<?php // team\n")
        ->and(File::get("{$this->directory}/app/Models/User.php"))->toBe("<?php // team user\n")
        ->and(File::get("{$this->directory}/routes/web.php"))->toBe("<?php // project routes\n")
        ->and(File::get("{$this->updateDirectory}/old/routes/web.php"))->toBe("<?php // base routes\n")
        ->and(File::get("{$this->updateDirectory}/new/routes/web.php"))->toBe("<?php // team routes\n")
        ->and("{$this->updateDirectory}/new/app/Models/Team.php")->not->toBeFile()
        ->and(File::get("{$this->updateDirectory}/changes.diff"))->toContain('diff --git a/app/Models/Team.php b/app/Models/Team.php')
        ->and(File::json("{$this->updateDirectory}/.starter-kit.json"))->toMatchArray([
            'version' => 'v1.4.0',
            'teams' => true,
            'removed' => ['Billing'],
        ])
        ->and(File::get("{$this->updateDirectory}/PROMPT.md"))
        ->toContain('# Add teams to this project')
        ->toContain('at **v1.4.0** without teams.')
        ->toContain("## Files to merge\n\n- `routes/web.php`\n")
        ->toContain("## Check new files against removed features\n\nThe CLI added these new team files to the project:\n\n- `app/Models/Team.php`\n")
        ->toContain("removed from this project on purpose. Never bring them back:\n\n- Billing\n")
        ->toContain('composer transform-types')
        ->toContain('Copy `.starter-kit-update/.starter-kit.json` to `.starter-kit.json` in the project root.')
        ->and("{$this->directory}/.starter-kit.json")->not->toBeFile()
        ->and($this->workingDirectory)->not->toBeDirectory();
});

it('stages a file teams add that the project already has without an old version', function (): void {
    fakeTeamsDownloads();

    createFiles($this->directory, ['app/Models/Team.php' => "<?php // project team\n"]);

    $addition = resolve(PrepareTeamsAddition::class)->handle(new StarterKitManifest(starterKitRelease()), UpdateDirectory::in($this->directory));

    expect($addition->unresolvedFiles)->toBe(['app/Models/Team.php', 'routes/web.php'])
        ->and("{$this->updateDirectory}/old/app/Models/Team.php")->not->toBeFile()
        ->and(File::get("{$this->updateDirectory}/new/app/Models/Team.php"))->toBe("<?php // team\n")
        ->and(File::get("{$this->directory}/app/Models/Team.php"))->toBe("<?php // project team\n");
});

it('only stages the manifest when every teams change applies cleanly', function (): void {
    fakeTeamsDownloads();

    File::put("{$this->directory}/routes/web.php", "<?php // base routes\n");

    $addition = resolve(PrepareTeamsAddition::class)->handle(new StarterKitManifest(starterKitRelease(kit: Kit::Api)), UpdateDirectory::in($this->directory));

    expect($addition->isComplete())->toBeTrue()
        ->and($addition->filesToReview)->toBeEmpty()
        ->and(File::get("{$this->directory}/routes/web.php"))->toBe("<?php // team routes\n")
        ->and(File::json("{$this->updateDirectory}/.starter-kit.json"))->toMatchArray(['teams' => true])
        ->and("{$this->updateDirectory}/PROMPT.md")->not->toBeFile()
        ->and("{$this->updateDirectory}/changes.diff")->not->toBeFile();
});

it('leaves out the type generation step for a kit without generated types', function (): void {
    fakeTeamsDownloads();

    resolve(PrepareTeamsAddition::class)->handle(new StarterKitManifest(starterKitRelease(kit: Kit::Livewire)), UpdateDirectory::in($this->directory));

    expect(File::get("{$this->updateDirectory}/PROMPT.md"))->not->toContain('transform-types')
        ->not->toContain('removed from this project on purpose');
});

it('fails without changing the project when the release has no teams overlay', function (): void {
    fakeTeamsDownloads(withOverlay: false);

    expect(fn () => resolve(PrepareTeamsAddition::class)->handle(new StarterKitManifest(starterKitRelease()), UpdateDirectory::in($this->directory)))
        ->toThrow(CouldNotApplyTeamsOverlay::class, 'The Vue starter kit v1.4.0 has no teams variant.')
        ->and(File::get("{$this->directory}/app/Models/User.php"))->toBe("<?php // base user\n")
        ->and($this->updateDirectory)->not->toBeDirectory()
        ->and($this->workingDirectory)->not->toBeDirectory();
});

it('cleans up the downloaded release when a download fails', function (): void {
    Process::fake([
        "*'create-project'*" => Process::result(exitCode: 1),
    ]);

    expect(fn () => resolve(PrepareTeamsAddition::class)->handle(new StarterKitManifest(starterKitRelease()), UpdateDirectory::in($this->directory)))
        ->toThrow(ProcessFailedException::class)
        ->and($this->workingDirectory)->not->toBeDirectory();
});

it('leaves out the merge section when only new team files need checking', function (): void {
    fakeTeamsDownloads();

    File::put("{$this->directory}/routes/web.php", "<?php // base routes\n");

    $addition = resolve(PrepareTeamsAddition::class)->handle(new StarterKitManifest(starterKitRelease(), ['Billing']), UpdateDirectory::in($this->directory));

    expect($addition->unresolvedFiles)->toBeEmpty()
        ->and($addition->filesToReview)->toBe(['app/Models/Team.php'])
        ->and($addition->isComplete())->toBeFalse()
        ->and(File::get("{$this->updateDirectory}/PROMPT.md"))
        ->toContain('need checking too.')
        ->toContain('## Check new files against removed features')
        ->not->toContain('## Files to merge')
        ->not->toContain('The files listed under "Files to merge"');
});

it('does not ask for new files to be checked when they were left for the agent', function (): void {
    fakeTeamsDownloads();

    createFiles($this->directory, ['app/Models/Team.php' => "<?php // project team\n"]);

    $addition = resolve(PrepareTeamsAddition::class)->handle(new StarterKitManifest(starterKitRelease(), ['Billing']), UpdateDirectory::in($this->directory));

    expect($addition->unresolvedFiles)->toContain('app/Models/Team.php')
        ->and($addition->filesToReview)->toBeEmpty();
});

it('reports that adding teams stopped partway when a file cannot be written', function (): void {
    fakeTeamsDownloads();

    File::put("{$this->directory}/routes/web.php", "<?php // base routes\n");

    chmod("{$this->directory}/routes/web.php", 0o444);

    expect(fn () => resolve(PrepareTeamsAddition::class)->handle(new StarterKitManifest(starterKitRelease()), UpdateDirectory::in($this->directory)))
        ->toThrow(CouldNotAddTeams::class, 'Adding teams stopped partway:')
        ->and(File::get("{$this->directory}/app/Models/Team.php"))->toBe("<?php // team\n")
        ->and(File::get("{$this->directory}/routes/web.php"))->toBe("<?php // base routes\n")
        ->and($this->updateDirectory)->not->toBeDirectory()
        ->and($this->workingDirectory)->not->toBeDirectory();
});
