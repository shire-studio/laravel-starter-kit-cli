<?php

declare(strict_types=1);

namespace App\Commands;

use App\Actions\CreateProject;
use App\Actions\DeleteDirectory;
use App\Actions\FindMissingTools;
use App\Actions\InitialiseGitRepository;
use App\Actions\InstallFrontend;
use App\Actions\ListStableStarterKitVersions;
use App\Actions\RecordStarterKitRelease;
use App\Actions\ResolveLatestStarterKitRelease;
use App\Data\StarterKitRelease;
use App\Enums\Kit;
use App\Exceptions\CouldNotResolveStarterKitRelease;
use Illuminate\Process\Exceptions\ProcessFailedException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

final class NewCommand extends Command
{
    protected $name = 'new';

    protected $description = 'Create a new project from a Shire Studio Laravel starter kit';

    public function interact(InputInterface $input, OutputInterface $output): void
    {
        if ($input->getArgument('name') === null) {
            $input->setArgument('name', text(
                label: 'What is the name of your project?',
                placeholder: 'E.g. example-app',
                required: true,
                validate: $this->validateName(...),
            ));
        }

        if ($input->getOption('kit') === null) {
            $input->setOption('kit', select(
                label: 'Which starter kit would you like to use?',
                options: Kit::options(),
            ));
        }

        $kitOption = $input->getOption('kit');

        $kit = is_string($kitOption) ? Kit::tryFrom($kitOption) : null;

        if ($kit?->hasFrontend() === true) {
            if ($input->getOption('pnpm') === null) {
                $input->setOption('pnpm', confirm('Would you like to install the frontend dependencies and build the assets?'));
            }
        }

        if ($input->getOption('git') === null) {
            $input->setOption('git', confirm('Would you like to initialise a git repository?'));
        }
    }

    public function handle(
        FindMissingTools $findMissingTools,
        ListStableStarterKitVersions $listStableStarterKitVersions,
        ResolveLatestStarterKitRelease $resolveLatestStarterKitRelease,
        DeleteDirectory $deleteDirectory,
        CreateProject $createProject,
        RecordStarterKitRelease $recordStarterKitRelease,
        InstallFrontend $installFrontend,
        InitialiseGitRepository $initialiseGitRepository,
    ): int {
        $name = $this->argument('name');
        $kitOption = $this->option('kit');
        $shouldInstallFrontend = $this->option('pnpm') === true;
        $shouldInitialiseGitRepository = $this->option('git') === true;

        $nameError = $this->validateName($name);

        if ($nameError !== null) {
            error($nameError);

            return self::FAILURE;
        }

        if (! is_string($kitOption)) {
            error("Choose a starter kit with --kit: {$this->kitChoiceList()}.");

            return self::FAILURE;
        }

        $kit = Kit::tryFrom($kitOption);

        if (! $kit instanceof Kit) {
            error("The kit must be one of: {$this->kitChoiceList()}.");

            return self::FAILURE;
        }

        if ($shouldInstallFrontend) {
            if (! $kit->hasFrontend()) {
                error("The {$kit->label()} starter kit has no frontend to install. Run new without --pnpm.");

                return self::FAILURE;
            }
        }

        $missingTools = $findMissingTools->handle($this->requiredTools($shouldInstallFrontend, $shouldInitialiseGitRepository));

        if ($missingTools !== []) {
            $missingToolList = implode(', ', $missingTools);

            error("The following required tools could not be found: {$missingToolList}.");

            return self::FAILURE;
        }

        $directory = $this->directoryFor($name);

        try {
            $release = spin(
                fn (): StarterKitRelease => $resolveLatestStarterKitRelease->handle($kit, $listStableStarterKitVersions->handle($kit)),
                "Resolving the latest {$kit->label()} starter kit release...",
            );

            info("Using the {$kit->label()} starter kit {$release->version}.");

            if (File::isDirectory($directory)) {
                warning("Removing the existing [{$name}] directory.");

                $deleteDirectory->handle($directory);
            }

            spin(
                fn () => $createProject->handle($release, $directory),
                "Creating [{$name}] from the {$kit->label()} starter kit...",
            );

            $recordStarterKitRelease->handle($release, $directory);

            info("Project created in [{$directory}].");

            if ($shouldInstallFrontend) {
                spin(
                    fn () => $installFrontend->handle($kit, $directory),
                    'Installing the frontend dependencies and building the assets...',
                );

                info('Frontend assets built.');
            }

            if ($shouldInitialiseGitRepository) {
                spin(
                    fn () => $initialiseGitRepository->handle($directory, "chore: create project from the {$kit->label()} starter kit {$release->version}"),
                    'Initialising a git repository...',
                );

                info('Git repository initialised.');
            }
        } catch (ProcessFailedException $exception) {
            $result = $exception->result;

            $this->output->write($result->errorOutput());
            $this->output->write($result->output());

            error("The command [{$result->command()}] failed with exit code {$result->exitCode()}.");

            return self::FAILURE;
        } catch (CouldNotResolveStarterKitRelease $exception) {
            error($exception->getMessage());

            return self::FAILURE;
        }

        note(implode(PHP_EOL, $this->nextSteps($name, $kit, $shouldInstallFrontend)));

        outro('Your project is ready.');

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{0: string, 1: int, 2: string}>
     */
    public function getArguments(): array
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the project'],
        ];
    }

    /**
     * @return array<int, array{0: string, 1: ?string, 2: int, 3: string}>
     */
    public function getOptions(): array
    {
        return [
            ['kit', null, InputOption::VALUE_REQUIRED, "The starter kit to use: {$this->kitChoiceList()}"],
            ['pnpm', null, InputOption::VALUE_NEGATABLE, 'Install the frontend dependencies and build the assets'],
            ['git', null, InputOption::VALUE_NEGATABLE, 'Initialise a git repository'],
            ['force', 'f', InputOption::VALUE_NONE, 'Overwrite the project directory if it already exists'],
        ];
    }

    private function validateName(string $name): ?string
    {
        if (preg_match('/^[a-z0-9][a-z0-9._-]*$/i', $name) !== 1) {
            return 'The name may only contain letters, numbers, dashes, underscores and dots, and must start with a letter or number.';
        }

        if (File::isDirectory($this->directoryFor($name))) {
            if ($this->option('force') !== true) {
                return "The directory [{$name}] already exists. Use --force to overwrite it.";
            }
        }

        return null;
    }

    private function directoryFor(string $name): string
    {
        $workingDirectory = getcwd();

        return "{$workingDirectory}/{$name}";
    }

    /**
     * @return list<string>
     */
    private function requiredTools(bool $shouldInstallFrontend, bool $shouldInitialiseGitRepository): array
    {
        return [
            'php',
            'composer',
            ...($shouldInstallFrontend ? ['pnpm'] : []),
            ...($shouldInitialiseGitRepository ? ['git'] : []),
        ];
    }

    /**
     * @return list<string>
     */
    private function nextSteps(string $name, Kit $kit, bool $shouldInstallFrontend): array
    {
        $needsFrontendInstall = $kit->hasFrontend() && ! $shouldInstallFrontend;

        return [
            'Next steps:',
            "  cd {$name}",
            ...($needsFrontendInstall ? ['  pnpm install'] : []),
            '  composer dev',
        ];
    }

    private function kitChoiceList(): string
    {
        return Arr::join(array_column(Kit::cases(), 'value'), ', ', ' or ');
    }
}
