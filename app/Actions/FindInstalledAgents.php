<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\Agent;

final readonly class FindInstalledAgents
{
    public function __construct(
        private FindMissingTools $findMissingTools,
    ) {}

    /**
     * @return list<Agent>
     */
    public function handle(): array
    {
        $missingBinaries = $this->findMissingTools->handle(array_map(
            fn (Agent $agent): string => $agent->binary(),
            Agent::cases(),
        ));

        return array_values(array_filter(
            Agent::cases(),
            fn (Agent $agent): bool => ! in_array($agent->binary(), $missingBinaries, true),
        ));
    }
}
