<?php

declare(strict_types=1);

namespace BassamShoukry\LaravelChatwoot\Resources;

use Illuminate\Support\Collection;

final class CannedResponseResource extends BaseResource
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function list(): Collection
    {
        $response = $this->client->get($this->accountPath('canned_responses'));

        return collect($this->arrayOfArrays(is_array($response) ? $response : []));
    }

    /**
     * @return array<string, mixed>
     */
    public function create(string $shortCode, string $content): array
    {
        return $this->client->post($this->accountPath('canned_responses'), [
            'short_code' => $shortCode,
            'content'    => $content,
        ]);
    }

    public function delete(int $id): void
    {
        $this->client->delete($this->accountPath("canned_responses/{$id}"));
    }
}
