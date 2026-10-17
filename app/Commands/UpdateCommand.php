<?php

declare(strict_types=1);

namespace App\Commands;

use App\Actions\CompleteStarterKitUpdate;
use App\Actions\DeleteDirectory;
use App\Actions\FindInstalledAgents;
use App\Actions\FindMissingTools;
use App\Actions\IgnoreUpdateDirectory;
use App\Actions\IsGitRepository;
use App\Actions\LaunchAgent;
use App\Actions\ListStableStarterKitVersions;
use App\Actions\ListUncommittedChanges;
use App\Actions\PrepareStarterKitDetection;
use App\Actions\PrepareStarterKitUpdate;
use App\Actions\ReadDetectedStarterKitVersion;
use App\Actions\ReadProposedRemovedFeatures;
use App\Actions\ReadStarterKitManifest;
use App\Actions\RecordStarterKitRelease;
use App\Actions\ResolveLatestStarterKitRelease;
use App\Actions\ResolveStarterKitRelease;
use App\Actions\StageRemovedFeatures;
use App\Data\StarterKitManifest;
use App\Data\StarterKitRelease;
use App\Data\StarterKitUpdate;
use App\Data\UpdateDirectory;
use App\Enums\Agent;
use App\Exceptions\CouldNotReadStarterKitManifest;
use App\Exceptions\CouldNotResolveStarterKitRelease;
use Illuminate\Process\Exceptions\ProcessFailedException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Console\Input\InputOption;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\note;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\warning;

final class UpdateCommand extends Command
{
    private const string NoAgentChoice = 'none';

    protected $name = 'update';

    protected $description = 'Bring newer starter kit changes into this project with the help of a coding agent';

    public function handle(
        FindMissingTools $findMissingTools,
        IsGitRepository $isGitRepository,
        ListUncommittedChanges $listUncommittedChanges,
        ReadStarterKitManifest $readStarterKitManifest,
        ListStableStarterKitVersions $listStableStarterKitVersions,
        ResolveLatestStarterKitRelease $resolveLatestStarterKitRelease,
        FindInstalledAgents $findInstalledAgents,
        IgnoreUpdateDirectory $ignoreUpdateDirectory,
        PrepareStarterKitDetection $prepareStarterKitDetection,
        LaunchAgent $launchAgent,
        ReadDetectedStarterKitVersion $readDetectedStarterKitVersion,
        DeleteDirectory $deleteDirectory,
        ResolveStarterKitRelease $resolveStarterKitRelease,
        RecordStarterKitRelease $recordStarterKitRelease,
        PrepareStarterKitUpdate $prepareStarterKitUpdate,
        ReadProposedRemovedFeatures $readProposedRemovedFeatures,
        StageRemovedFeatures $stageRemovedFeatures,
        CompleteStarterKitUpdate $completeStarterKitUpdate,
    ): int {
        $directory = (string) getcwd();
        $updateDirectory = UpdateDirectory::in($directory);
        $manifestFilename = $updateDirectory->manifestFilename;
        $agentOption = $this->option('agent');

        if (is_string($agentOption)) {
            if (! in_array($agentOption, $this->agentChoices(), true)) {
                $agentChoiceList = implode(', ', $this->agentChoices());

                error("The agent must be one of: {$agentChoiceList}.");

                return self::FAILURE;
            }
        }

        $requestedAgent = is_string($agentOption) ? Agent::tryFrom($agentOption) : null;

        if ($requestedAgent instanceof Agent) {
            if (! $this->input->isInteractive()) {
                error('An agent session needs an interactive terminal. Run update without --no-interaction, or use --agent=none.');

                return self::FAILURE;
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

            $versions = spin(
                $listStableStarterKitVersions->handle(...),
                'Listing the stable starter kit releases...',
            );

            if ($manifest instanceof StarterKitManifest) {
                if (! in_array($manifest->release->version, $versions, true)) {
                    error("Starter kit {$manifest->release->version} recorded in [{$manifestFilename}] is not a stable release. Set it to the stable release this project was created from, then run update again.");

                    return self::FAILURE;
                }
            }

            $latestRelease = spin(
                $resolveLatestStarterKitRelease->handle(...),
                'Resolving the latest starter kit release...',
            );

            if ($manifest?->release->version === $latestRelease->version) {
                info("The project is already up to date with starter kit {$latestRelease->version}.");

                return self::SUCCESS;
            }

            $installedAgents = $findInstalledAgents->handle();

            $agent = is_string($agentOption)
                ? $requestedAgent
                : $this->chooseAgent($installedAgents);

            if ($agent instanceof Agent) {
                if (! in_array($agent, $installedAgents, true)) {
                    error("{$agent->label()} could not be found. Install it or choose another agent.");

                    return self::FAILURE;
                }
            }

            $ignoreUpdateDirectory->handle($updateDirectory);

            if (! $manifest instanceof StarterKitManifest) {
                if (! $agent instanceof Agent) {
                    error("No [{$manifestFilename}] was found, so the starter kit version this project was created from is unknown. Run update again and choose an agent to detect it, or add [{$manifestFilename}] by hand.");

                    return self::FAILURE;
                }

                warning("No [{$manifestFilename}] was found. {$agent->label()} will work out which starter kit version this project was created from.");

                $prepareStarterKitDetection->handle($updateDirectory, $latestRelease, $versions);

                $this->handOver($launchAgent, $agent, $updateDirectory, $updateDirectory->detectionPrompt());

                $detectedVersion = $readDetectedStarterKitVersion->handle($updateDirectory);

                if ($detectedVersion === null || ! in_array($detectedVersion, $versions, true)) {
                    $deleteDirectory->handle($updateDirectory->path());

                    error("{$agent->label()} did not record a starter kit version that exists. Add [{$manifestFilename}] by hand or run update again.");

                    return self::FAILURE;
                }

                if (! confirm("{$agent->label()} detected starter kit {$detectedVersion}. Use it as the base for the update?")) {
                    $deleteDirectory->handle($updateDirectory->path());

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
                fn (): StarterKitUpdate => $prepareStarterKitUpdate->handle($manifest, $latestRelease, $updateDirectory),
                "Preparing the changes from starter kit {$fromVersion} to {$toVersion}...",
            );

            if (! $update->diff->hasChanges()) {
                $completeStarterKitUpdate->handle($updateDirectory);

                outro("Starter kit {$toVersion} has no changes for this project, so it has been marked as updated.");

                return self::SUCCESS;
            }

            info("Prepared {$update->diff->changedFileCount()} changed files from starter kit {$fromVersion} to {$toVersion} in [{$updateDirectory->name}].");

            if ($update->removedFiles !== []) {
                $removedFileCount = count($update->removedFiles);

                info("Found {$removedFileCount} starter kit files this project has removed. They will stay removed.");
            }

            if (! $agent instanceof Agent) {
                note(implode(PHP_EOL, [
                    "Prepared in [{$updateDirectory->name}]:",
                    '  PROMPT.md       how to apply the update',
                    '  changes.diff    the upstream changes',
                    '  old/ and new/   both versions of every changed file',
                    ...($update->removedFiles === [] ? [] : ['  REMOVED.md      starter kit files this project removed']),
                    '',
                    'Next steps:',
                    "  Follow {$updateDirectory->relative($updateDirectory->prompt())} yourself, or hand it to any coding agent.",
                ]));

                outro('The update is ready to apply.');

                return self::SUCCESS;
            }

            $this->handOver($launchAgent, $agent, $updateDirectory, $updateDirectory->prompt());

            note(implode(PHP_EOL, $this->changedFileLines($listUncommittedChanges->handle($directory))));

            $proposedRemovedFeatures = $readProposedRemovedFeatures->handle($manifest, $updateDirectory);

            if ($proposedRemovedFeatures !== []) {
                /** @var list<string> $acceptedRemovedFeatures */
                $acceptedRemovedFeatures = multiselect(
                    label: 'Which removed features should be recorded so future updates leave them out?',
                    options: $proposedRemovedFeatures,
                    default: $proposedRemovedFeatures,
                );

                $stageRemovedFeatures->handle($updateDirectory, $latestRelease, [...$manifest->removedFeatures, ...$acceptedRemovedFeatures]);
            }

            if (! confirm("Mark the project as updated to starter kit {$toVersion}?")) {
                $stagedManifest = $updateDirectory->relative($updateDirectory->manifest());

                warning("The project is still marked as starter kit {$fromVersion}. To finish by hand, copy [{$stagedManifest}] to [{$manifestFilename}] and delete [{$updateDirectory->name}].");

                return self::SUCCESS;
            }

            $completeStarterKitUpdate->handle($updateDirectory);
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

    private function handOver(LaunchAgent $launchAgent, Agent $agent, UpdateDirectory $updateDirectory, string $instructionsPath): void
    {
        $exitCode = $launchAgent->handle($agent, $updateDirectory->projectDirectory, $updateDirectory->relative($instructionsPath));

        if ($exitCode !== 0) {
            warning("{$agent->label()} exited with code {$exitCode}.");
        }
    }

    /**
     * @param  list<Agent>  $installedAgents
     */
    private function chooseAgent(array $installedAgents): ?Agent
    {
        if (! $this->input->isInteractive()) {
            return null;
        }

        $choice = select(
            label: 'Which agent should apply the update?',
            options: [
                ...collect($installedAgents)->mapWithKeys(fn (Agent $agent): array => [$agent->value => $agent->label()])->all(),
                self::NoAgentChoice => 'None, just give me the files',
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
            self::NoAgentChoice,
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
