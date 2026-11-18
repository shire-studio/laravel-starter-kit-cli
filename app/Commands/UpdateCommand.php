<?php

declare(strict_types=1);

namespace App\Commands;

use App\Actions\CompleteStarterKitUpdate;
use App\Actions\DeleteDirectory;
use App\Actions\FindInstalledAgents;
use App\Actions\FindMissingTools;
use App\Actions\GuessStarterKit;
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
use App\Commands\Concerns\ChecksProjectBeforeChanges;
use App\Commands\Concerns\HandsOverToAgents;
use App\Commands\Concerns\ReportsFailedProcesses;
use App\Data\StarterKitManifest;
use App\Data\StarterKitRelease;
use App\Data\StarterKitUpdate;
use App\Data\UpdateDirectory;
use App\Enums\Agent;
use App\Enums\Kit;
use App\Exceptions\CouldNotApplyTeamsOverlay;
use App\Exceptions\CouldNotCompleteStarterKitUpdate;
use App\Exceptions\CouldNotReadStarterKitManifest;
use App\Exceptions\CouldNotResolveStarterKitRelease;
use Illuminate\Process\Exceptions\ProcessFailedException;
use Illuminate\Support\Arr;
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
    use ChecksProjectBeforeChanges;
    use HandsOverToAgents;
    use ReportsFailedProcesses;

    protected $name = 'update';

    protected $description = 'Bring newer starter kit changes into this project with the help of a coding agent';

    public function handle(
        FindMissingTools $findMissingTools,
        IsGitRepository $isGitRepository,
        ListUncommittedChanges $listUncommittedChanges,
        ReadStarterKitManifest $readStarterKitManifest,
        GuessStarterKit $guessStarterKit,
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
        $kitOption = $this->option('kit');

        if (is_string($kitOption)) {
            if (Kit::tryFrom($kitOption) === null) {
                error("The kit must be one of: {$this->kitChoiceList()}.");

                return self::FAILURE;
            }
        }

        $requestedKit = is_string($kitOption) ? Kit::tryFrom($kitOption) : null;

        $agentOptionProblem = $this->invalidAgentOption();

        if ($agentOptionProblem !== null) {
            error($agentOptionProblem);

            return self::FAILURE;
        }

        $requestedAgent = is_string($agentOption) ? Agent::tryFrom($agentOption) : null;

        $projectProblem = $this->projectProblem($findMissingTools, $isGitRepository, $listUncommittedChanges, $directory, 'the update');

        if ($projectProblem !== null) {
            error($projectProblem);

            return self::FAILURE;
        }

        $pendingChangesProblem = $this->pendingChangesProblem($updateDirectory);

        if ($pendingChangesProblem !== null) {
            error($pendingChangesProblem);

            return self::FAILURE;
        }

        try {
            $manifest = $readStarterKitManifest->handle($directory);

            if ($manifest instanceof StarterKitManifest) {
                if ($requestedKit instanceof Kit) {
                    if ($requestedKit !== $manifest->release->kit) {
                        error("[{$manifestFilename}] records the {$manifest->release->kit->label()} starter kit, not {$requestedKit->label()}. Run update without --kit.");

                        return self::FAILURE;
                    }
                }
            }

            $kit = $manifest?->release->kit ?? $this->chooseKit($requestedKit, $guessStarterKit->handle($directory));

            if (! $kit instanceof Kit) {
                error("No [{$manifestFilename}] was found, so the starter kit this project was created from is unknown. Pass it with --kit: {$this->kitChoiceList()}.");

                return self::FAILURE;
            }

            $versions = spin(
                fn (): array => $listStableStarterKitVersions->handle($kit),
                "Listing the stable {$kit->label()} starter kit releases...",
            );

            if ($manifest instanceof StarterKitManifest) {
                if (! in_array($manifest->release->version, $versions, true)) {
                    error("Starter kit {$manifest->release->version} recorded in [{$manifestFilename}] is not a stable release. Set it to the stable release this project was created from, then run update again.");

                    return self::FAILURE;
                }
            }

            $latestRelease = spin(
                fn (): StarterKitRelease => $resolveLatestStarterKitRelease->handle($kit, $versions),
                'Resolving the latest starter kit release...',
            );

            if ($manifest?->release->version === $latestRelease->version) {
                info("The project is already up to date with starter kit {$latestRelease->version}.");

                return self::SUCCESS;
            }

            $installedAgents = $findInstalledAgents->handle();

            $agent = is_string($agentOption)
                ? $requestedAgent
                : $this->chooseAgent('Which agent should apply the update?', $installedAgents);

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
                    fn (): StarterKitRelease => $resolveStarterKitRelease->handle($kit, $detectedVersion),
                    "Resolving starter kit {$detectedVersion}...",
                );

                $manifest = new StarterKitManifest($baseRelease);

                $recordStarterKitRelease->handle($manifest, $directory);

                info("Recorded starter kit {$detectedVersion} in [{$manifestFilename}].");
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
                    $this->preparedFileLine(basename($updateDirectory->prompt()), 'how to apply the update'),
                    $this->preparedFileLine(basename($updateDirectory->changes()), 'the upstream changes'),
                    $this->preparedFileLine(basename($updateDirectory->oldFiles()).'/ and '.basename($updateDirectory->newFiles()).'/', 'both versions of every changed file'),
                    ...($update->removedFiles === [] ? [] : [$this->preparedFileLine(basename($updateDirectory->removedFiles()), 'starter kit files this project removed')]),
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

                $stageRemovedFeatures->handle($updateDirectory, new StarterKitManifest($latestRelease, [...$manifest->removedFeatures, ...$acceptedRemovedFeatures], $manifest->teams));
            }

            if (! confirm("Mark the project as updated to starter kit {$toVersion}?")) {
                $stagedManifest = $updateDirectory->relative($updateDirectory->manifest());

                warning("The project is still marked as starter kit {$fromVersion}. To finish by hand, copy [{$stagedManifest}] to [{$manifestFilename}] and delete [{$updateDirectory->name}].");

                return self::SUCCESS;
            }

            $completeStarterKitUpdate->handle($updateDirectory);
        } catch (ProcessFailedException $exception) {
            return $this->reportFailedProcess($exception);
        } catch (CouldNotResolveStarterKitRelease|CouldNotReadStarterKitManifest|CouldNotApplyTeamsOverlay|CouldNotCompleteStarterKitUpdate $exception) {
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
            ['kit', null, InputOption::VALUE_REQUIRED, "The starter kit the project was created from, when it has no manifest: {$this->kitChoiceList()}"],
            ['force', 'f', InputOption::VALUE_NONE, 'Run even if the project has uncommitted changes'],
        ];
    }

    private function chooseKit(?Kit $requestedKit, ?Kit $guessedKit): ?Kit
    {
        if ($requestedKit instanceof Kit) {
            return $requestedKit;
        }

        if (! $this->input->isInteractive()) {
            return $guessedKit;
        }

        $choice = select(
            label: 'Which starter kit was this project created from?',
            options: Kit::options(),
            default: $guessedKit?->value,
        );

        return Kit::tryFrom((string) $choice);
    }

    private function kitChoiceList(): string
    {
        return Arr::join(array_column(Kit::cases(), 'value'), ', ', ' or ');
    }

    private function preparedFileLine(string $filename, string $description): string
    {
        return '  '.mb_str_pad($filename, 16).$description;
    }
}
