<?php

declare(strict_types=1);

namespace BassamShoukry\LaravelChatwoot\Resources;

use BassamShoukry\LaravelChatwoot\Data\Agent;
use Illuminate\Support\Collection;

final class AgentResource extends BaseResource
{
    /**
     * @return Collection<int, Agent>
     */
    public function list(): Collection
    {
        $response = $this->client->get($this->accountPath('agents'));

        return collect($this->arrayOfArrays(is_array($response['data'] ?? null) ? $response['data'] : $response))
            ->map(static fn (array $row): Agent => Agent::from($row));
    }
}
