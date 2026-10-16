<?php

declare(strict_types=1);

namespace App\Commands;

use App\Actions\CompleteStarterKitUpdate;
use App\Actions\DetectStarterKitVersion;
use App\Actions\FindInstalledAgents;
use App\Actions\FindMissingTools;
use App\Actions\IgnoreUpdateDirectory;
use App\Actions\IsGitRepository;
use App\Actions\LaunchAgent;
use App\Actions\ListStableStarterKitVersions;
use App\Actions\ListUncommittedChanges;
use App\Actions\PrepareStarterKitUpdate;
use App\Actions\ReadProposedRemovedFeatures;
use App\Actions\ReadStarterKitManifest;
use App\Actions\RecordStarterKitRelease;
use App\Actions\ResolveLatestStarterKitRelease;
use App\Actions\ResolveStarterKitRelease;
use App\Data\StarterKitManifest;
use App\Data\StarterKitRelease;
use App\Data\StarterKitUpdate;
use App\Enums\Agent;
use App\Exceptions\CouldNotReadStarterKitManifest;
use App\Exceptions\CouldNotResolveStarterKitRelease;
use Illuminate\Process\Exceptions\ProcessFailedException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Console\Input\InputOption;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\warning;

final class UpdateCommand extends Command
{
    protected $name = 'update';

    protected $description = 'Bring newer starter kit changes into this project with the help of a coding agent';

    public function handle(
        FindMissingTools $findMissingTools,
        IsGitRepository $isGitRepository,
        ListUncommittedChanges $listUncommittedChanges,
        ReadStarterKitManifest $readStarterKitManifest,
        ResolveLatestStarterKitRelease $resolveLatestStarterKitRelease,
        FindInstalledAgents $findInstalledAgents,
        IgnoreUpdateDirectory $ignoreUpdateDirectory,
        ListStableStarterKitVersions $listStableStarterKitVersions,
        DetectStarterKitVersion $detectStarterKitVersion,
        ResolveStarterKitRelease $resolveStarterKitRelease,
        RecordStarterKitRelease $recordStarterKitRelease,
        PrepareStarterKitUpdate $prepareStarterKitUpdate,
        LaunchAgent $launchAgent,
        ReadProposedRemovedFeatures $readProposedRemovedFeatures,
        CompleteStarterKitUpdate $completeStarterKitUpdate,
    ): int {
        $directory = (string) getcwd();
        $agentOption = $this->option('agent');
        $manifestFilename = Config::string('starter-kit.manifest_filename');
        $updateDirectoryName = Config::string('starter-kit.update_directory');

        if (is_string($agentOption)) {
            if (! in_array($agentOption, $this->agentChoices(), true)) {
                $agentChoiceList = implode(', ', $this->agentChoices());

                error("The agent must be one of: {$agentChoiceList}.");

                return self::FAILURE;
            }

            if ($agentOption !== 'none') {
                if (! $this->input->isInteractive()) {
                    error('An agent session needs an interactive terminal. Run update without --no-interaction, or use --agent=none.');

                    return self::FAILURE;
                }
            }
        }

        $missingTools = $findMissingTools->handle(['php', 'composer', 'git']);

        if ($missingTools !== []) {
            $missingToolList = implode(', ', $missingTools);

            error("The following required tools could not be found: {$missingToolList}.");

            return self::FAILURE;
        }

        if (File::missing("{$directory}/composer.json")) {
            error("No composer.json was found in [{$directory}]. Run update from the root of a project created from the starter kit.");

            return self::FAILURE;
        }

        if (! $isGitRepository->handle($directory)) {
            error("[{$directory}] is not a git repository. Commit the project to git first so the update can be reviewed and undone.");

            return self::FAILURE;
        }

        if ($this->option('force') !== true) {
            if ($listUncommittedChanges->handle($directory) !== []) {
                error('The project has uncommitted changes. Commit or stash them first so the update can be reviewed and undone, or use --force.');

                return self::FAILURE;
            }
        }

        try {
            $manifest = $readStarterKitManifest->handle($directory);

            $latestRelease = spin(
                $resolveLatestStarterKitRelease->handle(...),
                'Resolving the latest starter kit release...',
            );

            if ($manifest?->release->version === $latestRelease->version) {
                info("The project is already up to date with starter kit {$latestRelease->version}.");

                return self::SUCCESS;
            }

            $installedAgents = $findInstalledAgents->handle();

            $agent = $this->chooseAgent($installedAgents);

            if ($agent instanceof Agent) {
                if (! in_array($agent, $installedAgents, true)) {
                    error("{$agent->label()} could not be found. Install it or choose another agent.");

                    return self::FAILURE;
                }
            }

            $ignoreUpdateDirectory->handle($directory);

            if (! $manifest instanceof StarterKitManifest) {
                if (! $agent instanceof Agent) {
                    error("No [{$manifestFilename}] was found, so the starter kit version this project was created from is unknown. Run update again and choose an agent to detect it.");

                    return self::FAILURE;
                }

                warning("No [{$manifestFilename}] was found. {$agent->label()} will work out which starter kit version this project was created from.");

                $versions = $listStableStarterKitVersions->handle();

                $detectedVersion = $detectStarterKitVersion->handle($agent, $directory, $latestRelease, $versions);

                if ($detectedVersion === null || ! in_array($detectedVersion, $versions, true)) {
                    error("{$agent->label()} did not record a starter kit version that exists. Add [{$manifestFilename}] by hand or run update again.");

                    return self::FAILURE;
                }

                if (! confirm("{$agent->label()} detected starter kit {$detectedVersion}. Use it as the base for the update?")) {
                    error("Update cancelled. Add [{$manifestFilename}] by hand or run update again.");

                    return self::FAILURE;
                }

                $baseRelease = spin(
                    fn (): StarterKitRelease => $resolveStarterKitRelease->handle($detectedVersion),
                    "Resolving starter kit {$detectedVersion}...",
                );

                $recordStarterKitRelease->handle($baseRelease, $directory);

                info("Recorded starter kit {$detectedVersion} in [{$manifestFilename}].");

                $manifest = new StarterKitManifest($baseRelease);
            }

            $fromVersion = $manifest->release->version;
            $toVersion = $latestRelease->version;

            $update = spin(
                fn (): StarterKitUpdate => $prepareStarterKitUpdate->handle($manifest, $latestRelease, $directory, $agent),
                "Preparing the changes from starter kit {$fromVersion} to {$toVersion}...",
            );

            if (! $update->diff->hasChanges()) {
                $completeStarterKitUpdate->handle($manifest, $latestRelease, $directory);

                outro("Starter kit {$toVersion} has no changes for this project, so it has been marked as updated.");

                return self::SUCCESS;
            }

            info("Prepared {$update->diff->changedFileCount()} changed files from starter kit {$fromVersion} to {$toVersion} in [{$updateDirectoryName}].");

            if ($update->removedFiles !== []) {
                $removedFileCount = count($update->removedFiles);

                info("Found {$removedFileCount} starter kit files this project has removed. They will stay removed.");
            }

            if (! $agent instanceof Agent) {
                note(implode(PHP_EOL, [
                    "Prepared in [{$updateDirectoryName}]:",
                    '  PROMPT.md       how to apply the update',
                    '  changes.diff    the upstream changes',
                    '  old/ and new/   both versions of every changed file',
                    ...($update->removedFiles === [] ? [] : ['  REMOVED.md      starter kit files this project removed']),
                    '',
                    'Next steps:',
                    "  Follow {$updateDirectoryName}/PROMPT.md yourself, or hand it to any coding agent.",
                ]));

                outro('The update is ready to apply.');

                return self::SUCCESS;
            }

            if (! confirm("Hand the update over to {$agent->label()}?")) {
                warning("Update cancelled. The prepared changes are kept in [{$updateDirectoryName}].");

                return self::SUCCESS;
            }

            $exitCode = $launchAgent->handle($agent, $directory, "Read {$updateDirectoryName}/PROMPT.md and follow the instructions in it.");

            if ($exitCode !== 0) {
                warning("{$agent->label()} exited with code {$exitCode}.");
            }

            note(implode(PHP_EOL, $this->changedFileLines($listUncommittedChanges->handle($directory))));

            $newRemovedFeatures = $readProposedRemovedFeatures->handle($manifest, $directory);

            if ($newRemovedFeatures !== []) {
                note(implode(PHP_EOL, [
                    'Removed features to record:',
                    ...array_map(fn (string $removedFeature): string => "  {$removedFeature}", $newRemovedFeatures),
                ]));
            }

            if (! confirm("Mark the project as updated to starter kit {$toVersion}?")) {
                warning("The project is still marked as starter kit {$fromVersion}. The prepared changes are kept in [{$updateDirectoryName}].");

                return self::SUCCESS;
            }

            $completeStarterKitUpdate->handle($manifest, $latestRelease, $directory, $newRemovedFeatures);
        } catch (ProcessFailedException $exception) {
            $result = $exception->result;

            $this->output->write($result->errorOutput());
            $this->output->write($result->output());

            error("The command [{$result->command()}] failed with exit code {$result->exitCode()}.");

            return self::FAILURE;
        } catch (CouldNotResolveStarterKitRelease|CouldNotReadStarterKitManifest $exception) {
            error($exception->getMessage());

            return self::FAILURE;
        }

        note(implode(PHP_EOL, [
            'Next steps:',
            '  Review the changes with git diff',
            "  git add --all && git commit --message=\"chore: update starter kit from {$fromVersion} to {$toVersion}\"",
        ]));

        outro("The project is updated to starter kit {$toVersion}.");

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{0: string, 1: ?string, 2: int, 3: string}>
     */
    public function getOptions(): array
    {
        $agentChoiceList = Arr::join($this->agentChoices(), ', ', ' or ');

        return [
            ['agent', null, InputOption::VALUE_REQUIRED, "The agent to hand the update to: {$agentChoiceList}"],
            ['force', 'f', InputOption::VALUE_NONE, 'Run even if the project has uncommitted changes'],
        ];
    }

    /**
     * @param  list<Agent>  $installedAgents
     */
    private function chooseAgent(array $installedAgents): ?Agent
    {
        $agentOption = $this->option('agent');

        if (is_string($agentOption)) {
            return Agent::tryFrom($agentOption);
        }

        if (! $this->input->isInteractive()) {
            return null;
        }

        $choice = select(
            label: 'Which agent should apply the update?',
            options: [
                ...collect($installedAgents)->mapWithKeys(fn (Agent $agent): array => [$agent->value => $agent->label()])->all(),
                'none' => 'None, just give me the files',
            ],
        );

        return Agent::tryFrom((string) $choice);
    }

    /**
     * @return list<string>
     */
    private function agentChoices(): array
    {
        return [
            ...array_map(fn (Agent $agent): string => $agent->value, Agent::cases()),
            'none',
        ];
    }

    /**
     * @param  list<string>  $changedFiles
     * @return list<string>
     */
    private function changedFileLines(array $changedFiles): array
    {
        if ($changedFiles === []) {
            return ['No files were changed.'];
        }

        return [
            'Changed files:',
            ...array_map(fn (string $changedFile): string => "  {$changedFile}", $changedFiles),
        ];
    }
}
