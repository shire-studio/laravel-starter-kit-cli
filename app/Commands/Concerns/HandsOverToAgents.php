<?php

declare(strict_types=1);

namespace App\Commands\Concerns;

use App\Actions\LaunchAgent;
use App\Data\UpdateDirectory;
use App\Enums\Agent;

use function Laravel\Prompts\select;
use function Laravel\Prompts\warning;

trait HandsOverToAgents
{
    private const string NO_AGENT_CHOICE = 'none';

    private function invalidAgentOption(): ?string
    {
        $agentOption = $this->option('agent');

        if (! is_string($agentOption)) {
            return null;
        }

        if (! in_array($agentOption, $this->agentChoices(), true)) {
            $agentChoiceList = implode(', ', $this->agentChoices());

            return "The agent must be one of: {$agentChoiceList}.";
        }

        if (Agent::tryFrom($agentOption) instanceof Agent && ! $this->input->isInteractive()) {
            return "An agent session needs an interactive terminal. Run {$this->getName()} without --no-interaction, or use --agent=none.";
        }

        return null;
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
    private function chooseAgent(string $label, array $installedAgents): ?Agent
    {
        if (! $this->input->isInteractive()) {
            return null;
        }

        $choice = select(
            label: $label,
            options: [
                ...collect($installedAgents)->mapWithKeys(fn (Agent $agent): array => [$agent->value => $agent->label()])->all(),
                self::NO_AGENT_CHOICE => 'None, just give me the files',
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
            self::NO_AGENT_CHOICE,
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
