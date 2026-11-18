<?php

declare(strict_types=1);

namespace App\Commands;

use App\Actions\CompleteStarterKitUpdate;
use App\Actions\FindInstalledAgents;
use App\Actions\FindMissingTools;
use App\Actions\IgnoreUpdateDirectory;
use App\Actions\IsGitRepository;
use App\Actions\LaunchAgent;
use App\Actions\ListStableStarterKitVersions;
use App\Actions\ListUncommittedChanges;
use App\Actions\PrepareTeamsAddition;
use App\Actions\ReadStarterKitManifest;
use App\Commands\Concerns\ChecksProjectBeforeChanges;
use App\Commands\Concerns\HandsOverToAgents;
use App\Commands\Concerns\ReportsFailedProcesses;
use App\Data\StarterKitManifest;
use App\Data\TeamsAddition;
use App\Data\UpdateDirectory;
use App\Enums\Agent;
use App\Exceptions\CouldNotAddTeams;
use App\Exceptions\CouldNotApplyTeamsOverlay;
use App\Exceptions\CouldNotCompleteStarterKitUpdate;
use App\Exceptions\CouldNotReadStarterKitManifest;
use App\Exceptions\CouldNotResolveStarterKitRelease;
use Illuminate\Process\Exceptions\ProcessFailedException;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Console\Input\InputOption;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\warning;

final class TeamsCommand extends Command
{
    use ChecksProjectBeforeChanges;
    use HandsOverToAgents;
    use ReportsFailedProcesses;

    protected $name = 'teams';

    protected $description = 'Add teams to a project created from the starter kit without them';

    public function handle(
        FindMissingTools $findMissingTools,
        IsGitRepository $isGitRepository,
        ListUncommittedChanges $listUncommittedChanges,
        ReadStarterKitManifest $readStarterKitManifest,
        ListStableStarterKitVersions $listStableStarterKitVersions,
        FindInstalledAgents $findInstalledAgents,
        IgnoreUpdateDirectory $ignoreUpdateDirectory,
        PrepareTeamsAddition $prepareTeamsAddition,
        LaunchAgent $launchAgent,
        CompleteStarterKitUpdate $completeStarterKitUpdate,
    ): int {
        $directory = (string) getcwd();
        $updateDirectory = UpdateDirectory::in($directory);
        $manifestFilename = $updateDirectory->manifestFilename;
        $agentOption = $this->option('agent');

        $agentOptionProblem = $this->invalidAgentOption();

        if ($agentOptionProblem !== null) {
            error($agentOptionProblem);

            return self::FAILURE;
        }

        $requestedAgent = is_string($agentOption) ? Agent::tryFrom($agentOption) : null;

        $projectProblem = $this->projectProblem($findMissingTools, $isGitRepository, $listUncommittedChanges, $directory, 'adding teams');

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

            if (! $manifest instanceof StarterKitManifest) {
                error("No [{$manifestFilename}] was found, so the starter kit release this project was created from is unknown. Run update first to record it.");

                return self::FAILURE;
            }

            $release = $manifest->release;
            $kit = $release->kit;

            if ($manifest->teams) {
                info('The project already has teams.');

                return self::SUCCESS;
            }

            if (! $kit->hasTeams()) {
                error("The {$kit->label()} starter kit has no teams variant.");

                return self::FAILURE;
            }

            $authenticationFeatures = $this->removedAuthenticationFeatures($manifest);

            if ($authenticationFeatures !== []) {
                $authenticationFeatureList = implode(', ', $authenticationFeatures);

                warning("[{$manifestFilename}] records removed features that mention login, registration or authentication: {$authenticationFeatureList}. Teams need login and registration.");

                if (! $this->input->isInteractive() || ! confirm('Add teams anyway?', default: false)) {
                    error('Teams were not added.');

                    return self::FAILURE;
                }
            }

            $versions = spin(
                fn (): array => $listStableStarterKitVersions->handle($kit),
                "Listing the stable {$kit->label()} starter kit releases...",
            );

            if (! in_array($release->version, $versions, true)) {
                error("Starter kit {$release->version} recorded in [{$manifestFilename}] is not a stable release. Set it to the stable release this project was created from, then run teams again.");

                return self::FAILURE;
            }

            $installedAgents = $findInstalledAgents->handle();

            if ($requestedAgent instanceof Agent) {
                if (! in_array($requestedAgent, $installedAgents, true)) {
                    error("{$requestedAgent->label()} could not be found. Install it or choose another agent.");

                    return self::FAILURE;
                }
            }

            $ignoreUpdateDirectory->handle($updateDirectory);

            $addition = spin(
                fn (): TeamsAddition => $prepareTeamsAddition->handle($manifest, $updateDirectory),
                "Adding teams from the {$kit->label()} starter kit {$release->version}...",
            );

            $appliedFileCount = count($addition->appliedFiles);

            info("Applied the team changes to {$appliedFileCount} files.");

            if ($addition->isComplete()) {
                $completeStarterKitUpdate->handle($updateDirectory);

                return $this->finish($addition);
            }

            $unresolvedFileCount = count($addition->unresolvedFiles);
            $fileToReviewCount = count($addition->filesToReview);

            note(implode(PHP_EOL, [
                ...($addition->unresolvedFiles === [] ? [] : [
                    "{$unresolvedFileCount} files this project changed need the team changes merged by hand:",
                    ...array_map(fn (string $unresolvedFile): string => "  {$unresolvedFile}", $addition->unresolvedFiles),
                ]),
                ...($addition->filesToReview === [] ? [] : [
                    "{$fileToReviewCount} new team files need checking against the features this project removed.",
                ]),
            ]));

            $agent = is_string($agentOption)
                ? $requestedAgent
                : $this->chooseAgent('Which agent should finish adding teams?', $installedAgents);

            if (! $agent instanceof Agent) {
                note(implode(PHP_EOL, [
                    'Next steps:',
                    "  Follow {$updateDirectory->relative($updateDirectory->prompt())} yourself, or hand it to any coding agent.",
                ]));

                outro('The rest of the team changes are ready to finish.');

                return self::SUCCESS;
            }

            $this->handOver($launchAgent, $agent, $updateDirectory, $updateDirectory->prompt());

            note(implode(PHP_EOL, $this->changedFileLines($listUncommittedChanges->handle($directory))));

            if (! confirm('Mark the project as having teams?')) {
                $stagedManifest = $updateDirectory->relative($updateDirectory->manifest());

                warning("The project is still marked as having no teams. To finish by hand, copy [{$stagedManifest}] to [{$manifestFilename}] and delete [{$updateDirectory->name}].");

                return self::SUCCESS;
            }

            $completeStarterKitUpdate->handle($updateDirectory);
        } catch (ProcessFailedException $exception) {
            return $this->reportFailedProcess($exception);
        } catch (CouldNotApplyTeamsOverlay $exception) {
            $hint = $exception->releaseHasNoTeams ? ' Run update first to move the project to a release with teams.' : '';

            error("{$exception->getMessage()}{$hint}");

            return self::FAILURE;
        } catch (CouldNotResolveStarterKitRelease|CouldNotReadStarterKitManifest|CouldNotAddTeams|CouldNotCompleteStarterKitUpdate $exception) {
            error($exception->getMessage());

            return self::FAILURE;
        }

        return $this->finish($addition);
    }

    /**
     * @return array<int, array{0: string, 1: ?string, 2: int, 3: string}>
     */
    public function getOptions(): array
    {
        $agentChoiceList = Arr::join($this->agentChoices(), ', ', ' or ');

        return [
            ['agent', null, InputOption::VALUE_REQUIRED, "The agent to merge the team changes into files the project changed: {$agentChoiceList}"],
            ['force', 'f', InputOption::VALUE_NONE, 'Run even if the project has uncommitted changes'],
        ];
    }

    /**
     * @return list<string>
     */
    private function removedAuthenticationFeatures(StarterKitManifest $manifest): array
    {
        return array_values(array_filter(
            $manifest->removedFeatures,
            fn (string $removedFeature): bool => Str::contains($removedFeature, ['login', 'registration', 'authentication'], ignoreCase: true),
        ));
    }

    private function finish(TeamsAddition $addition): int
    {
        $kit = $addition->release->kit;

        note(implode(PHP_EOL, [
            'Next steps:',
            '  composer dump-autoload',
            '  php artisan migrate',
            ...($kit->generatesTypes() ? ['  composer transform-types'] : []),
            ...($kit->hasFrontend() ? ['  pnpm run build'] : []),
            '  Review the changes with git diff, then run the tests',
            "  git add --all && git commit --message=\"feat: add teams from the {$kit->label()} starter kit {$addition->release->version}\"",
        ]));

        outro('The project has teams.');

        return self::SUCCESS;
    }
}
