<?php

declare(strict_types=1);

use App\Actions\ApplyTeamsChanges;
use App\Data\StarterKitDiff;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

beforeEach(function (): void {
    $this->directory = temporaryDirectory();
    $this->baseDirectory = "{$this->directory}/base";
    $this->teamsDirectory = "{$this->directory}/teams";
    $this->projectDirectory = "{$this->directory}/project";

    Process::preventStrayProcesses(false);
});

afterEach(function (): void {
    File::deleteDirectory($this->directory);
});

function applyTeamsChanges(array $addedFiles = [], array $modifiedFiles = [], array $deletedFiles = []): array
{
    $test = test();

    return resolve(ApplyTeamsChanges::class)->handle(
        new StarterKitDiff('', $addedFiles, $modifiedFiles, $deletedFiles),
        $test->baseDirectory,
        $test->teamsDirectory,
        $test->projectDirectory,
    );
}

it('adds the files teams add', function (): void {
    createFiles($this->teamsDirectory, ['app/Models/Team.php' => "<?php // team\n", 'bin/teams' => "#!/bin/sh\n"]);

    chmod("{$this->teamsDirectory}/bin/teams", 0o755);

    expect(applyTeamsChanges(addedFiles: ['app/Models/Team.php', 'bin/teams']))->toBeEmpty()
        ->and(File::get("{$this->projectDirectory}/app/Models/Team.php"))->toBe("<?php // team\n")
        ->and(fileperms("{$this->projectDirectory}/bin/teams") & 0o777)->toBe(0o755);
});

it('replaces a file the project has not changed', function (): void {
    createFiles($this->baseDirectory, ['app/Models/User.php' => "<?php // user\n"]);
    createFiles($this->teamsDirectory, ['app/Models/User.php' => "<?php // team user\n"]);
    createFiles($this->projectDirectory, ['app/Models/User.php' => "<?php // user\n"]);

    expect(applyTeamsChanges(modifiedFiles: ['app/Models/User.php']))->toBeEmpty()
        ->and(File::get("{$this->projectDirectory}/app/Models/User.php"))->toBe("<?php // team user\n");
});

it('merges the teams change into a file the project changed elsewhere', function (): void {
    createFiles($this->baseDirectory, ['routes/web.php' => "first\nsecond\nthird\nfourth\nfifth\n"]);
    createFiles($this->teamsDirectory, ['routes/web.php' => "first\nsecond\nthird\nfourth\nteams\n"]);
    createFiles($this->projectDirectory, ['routes/web.php' => "project\nsecond\nthird\nfourth\nfifth\n"]);

    expect(applyTeamsChanges(modifiedFiles: ['routes/web.php']))->toBeEmpty()
        ->and(File::get("{$this->projectDirectory}/routes/web.php"))->toBe("project\nsecond\nthird\nfourth\nteams\n");
});

it('leaves a file alone when the teams change conflicts with the project', function (): void {
    createFiles($this->baseDirectory, ['routes/web.php' => "first\n"]);
    createFiles($this->teamsDirectory, ['routes/web.php' => "teams\n"]);
    createFiles($this->projectDirectory, ['routes/web.php' => "project\n"]);

    expect(applyTeamsChanges(modifiedFiles: ['routes/web.php']))->toBe(['routes/web.php'])
        ->and(File::get("{$this->projectDirectory}/routes/web.php"))->toBe("project\n");
});

it('leaves a file the project removed for the agent', function (): void {
    createFiles($this->baseDirectory, ['resources/js/pages/Dashboard.vue' => "base\n"]);
    createFiles($this->teamsDirectory, ['resources/js/pages/Dashboard.vue' => "teams\n"]);

    expect(applyTeamsChanges(modifiedFiles: ['resources/js/pages/Dashboard.vue']))->toBe(['resources/js/pages/Dashboard.vue'])
        ->and("{$this->projectDirectory}/resources/js/pages/Dashboard.vue")->not->toBeFile();
});

it('leaves a file for the agent when the project has its own file where teams add one', function (): void {
    createFiles($this->teamsDirectory, ['app/Models/Team.php' => "<?php // team\n"]);
    createFiles($this->projectDirectory, ['app/Models/Team.php' => "<?php // project team\n"]);

    expect(applyTeamsChanges(addedFiles: ['app/Models/Team.php']))->toBe(['app/Models/Team.php'])
        ->and(File::get("{$this->projectDirectory}/app/Models/Team.php"))->toBe("<?php // project team\n");
});

it('accepts a file the project already has in its teams version', function (): void {
    createFiles($this->teamsDirectory, ['app/Models/Team.php' => "<?php // team\n"]);
    createFiles($this->projectDirectory, ['app/Models/Team.php' => "<?php // team\n"]);

    expect(applyTeamsChanges(addedFiles: ['app/Models/Team.php']))->toBeEmpty();
});

it('deletes a file teams delete when the project has not changed it', function (): void {
    createFiles($this->baseDirectory, ['resources/js/pages/Welcome.vue' => "base\n"]);
    createFiles($this->projectDirectory, ['resources/js/pages/Welcome.vue' => "base\n"]);

    expect(applyTeamsChanges(deletedFiles: ['resources/js/pages/Welcome.vue']))->toBeEmpty()
        ->and("{$this->projectDirectory}/resources/js/pages/Welcome.vue")->not->toBeFile();
});

it('keeps a file teams delete when the project changed it', function (): void {
    createFiles($this->baseDirectory, ['resources/js/pages/Welcome.vue' => "base\n"]);
    createFiles($this->projectDirectory, ['resources/js/pages/Welcome.vue' => "project\n"]);

    expect(applyTeamsChanges(deletedFiles: ['resources/js/pages/Welcome.vue']))->toBe(['resources/js/pages/Welcome.vue'])
        ->and("{$this->projectDirectory}/resources/js/pages/Welcome.vue")->toBeFile();
});

it('accepts a file teams delete that the project already removed', function (): void {
    createFiles($this->baseDirectory, ['resources/js/pages/Welcome.vue' => "base\n"]);

    expect(applyTeamsChanges(deletedFiles: ['resources/js/pages/Welcome.vue']))->toBeEmpty();
});

it('lists the files left for the agent in order', function (): void {
    createFiles($this->baseDirectory, ['routes/web.php' => "first\n", 'app/Models/User.php' => "user\n"]);
    createFiles($this->teamsDirectory, ['routes/web.php' => "teams\n", 'app/Models/User.php' => "team user\n"]);
    createFiles($this->projectDirectory, ['routes/web.php' => "project\n", 'app/Models/User.php' => "project user\n"]);

    expect(applyTeamsChanges(modifiedFiles: ['routes/web.php', 'app/Models/User.php']))->toBe(['app/Models/User.php', 'routes/web.php']);
});

it('makes a file executable when teams only change its executable bit', function (): void {
    createFiles($this->baseDirectory, ['bin/setup' => "#!/bin/sh\n"]);
    createFiles($this->teamsDirectory, ['bin/setup' => "#!/bin/sh\n"]);
    createFiles($this->projectDirectory, ['bin/setup' => "#!/bin/sh\n"]);

    chmod("{$this->baseDirectory}/bin/setup", 0o644);
    chmod("{$this->teamsDirectory}/bin/setup", 0o755);
    chmod("{$this->projectDirectory}/bin/setup", 0o644);

    expect(applyTeamsChanges(modifiedFiles: ['bin/setup']))->toBeEmpty()
        ->and(fileperms("{$this->projectDirectory}/bin/setup") & 0o777)->toBe(0o755);
});

it('removes the executable bit when teams remove it', function (): void {
    createFiles($this->baseDirectory, ['bin/setup' => "#!/bin/sh\n"]);
    createFiles($this->teamsDirectory, ['bin/setup' => "#!/bin/sh\n"]);
    createFiles($this->projectDirectory, ['bin/setup' => "#!/bin/sh\n"]);

    chmod("{$this->baseDirectory}/bin/setup", 0o755);
    chmod("{$this->teamsDirectory}/bin/setup", 0o644);
    chmod("{$this->projectDirectory}/bin/setup", 0o755);

    expect(applyTeamsChanges(modifiedFiles: ['bin/setup']))->toBeEmpty()
        ->and(fileperms("{$this->projectDirectory}/bin/setup") & 0o777)->toBe(0o644);
});

it('keeps the mode the project gave a file when teams do not change it', function (): void {
    createFiles($this->baseDirectory, ['routes/web.php' => "first\nsecond\nthird\nfourth\nfifth\n"]);
    createFiles($this->teamsDirectory, ['routes/web.php' => "first\nsecond\nthird\nfourth\nteams\n"]);
    createFiles($this->projectDirectory, ['routes/web.php' => "project\nsecond\nthird\nfourth\nfifth\n"]);

    chmod("{$this->projectDirectory}/routes/web.php", 0o600);

    expect(applyTeamsChanges(modifiedFiles: ['routes/web.php']))->toBeEmpty()
        ->and(fileperms("{$this->projectDirectory}/routes/web.php") & 0o777)->toBe(0o600);
});

it('keeps the mode of a file teams add that the project already has', function (): void {
    createFiles($this->teamsDirectory, ['bin/teams' => "#!/bin/sh\n"]);
    createFiles($this->projectDirectory, ['bin/teams' => "#!/bin/sh\n"]);

    chmod("{$this->teamsDirectory}/bin/teams", 0o755);
    chmod("{$this->projectDirectory}/bin/teams", 0o755);

    expect(applyTeamsChanges(addedFiles: ['bin/teams']))->toBeEmpty()
        ->and(fileperms("{$this->projectDirectory}/bin/teams") & 0o777)->toBe(0o755);
});

it('leaves a file for the agent when the project has a directory in its place', function (): void {
    createFiles($this->baseDirectory, ['app/Teams.php' => "<?php // base\n"]);
    createFiles($this->teamsDirectory, ['app/Teams.php' => "<?php // teams\n", 'app/Models/Team.php' => "<?php // team\n"]);
    createFiles($this->projectDirectory, ['app/Teams.php/Example.php' => "<?php // project\n"]);

    expect(applyTeamsChanges(addedFiles: ['app/Models/Team.php'], modifiedFiles: ['app/Teams.php']))->toBe(['app/Teams.php'])
        ->and("{$this->projectDirectory}/app/Teams.php")->toBeDirectory()
        ->and("{$this->projectDirectory}/app/Models/Team.php")->toBeFile();
});

it('leaves a file for the agent when the project has a file where teams need a directory', function (): void {
    createFiles($this->teamsDirectory, ['app/Teams/Actions/CreateTeam.php' => "<?php // create team\n"]);
    createFiles($this->projectDirectory, ['app/Teams' => "project notes\n"]);

    expect(applyTeamsChanges(addedFiles: ['app/Teams/Actions/CreateTeam.php']))->toBe(['app/Teams/Actions/CreateTeam.php'])
        ->and(File::get("{$this->projectDirectory}/app/Teams"))->toBe("project notes\n");
});

it('leaves a deleted file for the agent when the project has a directory in its place', function (): void {
    createFiles($this->baseDirectory, ['resources/js/pages/Welcome.vue' => "base\n"]);
    createFiles($this->projectDirectory, ['resources/js/pages/Welcome.vue/index.vue' => "project\n"]);

    expect(applyTeamsChanges(deletedFiles: ['resources/js/pages/Welcome.vue']))->toBe(['resources/js/pages/Welcome.vue'])
        ->and("{$this->projectDirectory}/resources/js/pages/Welcome.vue")->toBeDirectory();
});
