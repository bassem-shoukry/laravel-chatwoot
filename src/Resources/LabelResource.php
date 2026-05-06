<?php

declare(strict_types=1);

namespace BassamShoukry\LaravelChatwoot\Resources;

use BassamShoukry\LaravelChatwoot\Data\Label;
use Illuminate\Support\Collection;

final class LabelResource extends BaseResource
{
    /**
     * @return Collection<int, Label>
     */
    public function list(): Collection
    {
        $response = $this->client->get($this->accountPath('labels'));
        $payload = $response['payload'] ?? $response['data'] ?? [];

        return collect($this->arrayOfArrays(is_array($payload) ? $payload : []))
            ->map(static fn (array $row): Label => Label::from($row));
    }

    public function create(string $title, ?string $description = null, ?string $color = null): Label
    {
        $response = $this->client->post($this->accountPath('labels'), array_filter([
            'title'       => $title,
            'description' => $description,
            'color'       => $color,
        ], static fn (mixed $v): bool => $v !== null));

        return Label::from(is_array($response['payload'] ?? null) ? $response['payload'] : $response);
    }
}
