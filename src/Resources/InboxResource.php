<?php

declare(strict_types=1);

namespace BassamShoukry\LaravelChatwoot\Resources;

use BassamShoukry\LaravelChatwoot\Data\Inbox;
use Illuminate\Support\Collection;

final class InboxResource extends BaseResource
{
    /**
     * @return Collection<int, Inbox>
     */
    public function list(): Collection
    {
        $response = $this->client->get($this->accountPath('inboxes'));
        $payload = $response['payload'] ?? $response['data'] ?? [];

        return collect($this->arrayOfArrays(is_array($payload) ? $payload : []))
            ->map(static fn (array $row): Inbox => Inbox::from($row));
    }

    public function find(int $inboxId): Inbox
    {
        $response = $this->client->get($this->accountPath("inboxes/{$inboxId}"));

        return Inbox::from($response);
    }
}
