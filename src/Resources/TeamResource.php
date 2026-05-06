<?php

declare(strict_types=1);

namespace BassamShoukry\LaravelChatwoot\Resources;

use BassamShoukry\LaravelChatwoot\Data\Team;
use Illuminate\Support\Collection;

final class TeamResource extends BaseResource
{
    /**
     * @return Collection<int, Team>
     */
    public function list(): Collection
    {
        $response = $this->client->get($this->accountPath('teams'));

        return collect($this->arrayOfArrays(is_array($response['data'] ?? null) ? $response['data'] : $response))
            ->map(static fn (array $row): Team => Team::from($row));
    }

    public function find(int $teamId): Team
    {
        $response = $this->client->get($this->accountPath("teams/{$teamId}"));

        return Team::from($response);
    }
}
