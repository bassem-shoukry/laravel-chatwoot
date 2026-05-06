<?php

declare(strict_types=1);

namespace BassamShoukry\LaravelChatwoot\Resources;

use BassamShoukry\LaravelChatwoot\Data\Contact;
use Illuminate\Support\Collection;

final class ContactResource extends BaseResource
{
    /**
     * @return Collection<int, Contact>
     */
    public function search(string $query, int $page = 1): Collection
    {
        $response = $this->client->get($this->accountPath('contacts/search'), [
            'q'    => $query,
            'page' => $page,
        ]);

        $payload = $response['payload'] ?? [];

        return collect($this->arrayOfArrays(is_array($payload) ? $payload : []))
            ->map(static fn (array $row): Contact => Contact::from($row));
    }

    public function find(int $contactId): Contact
    {
        $response = $this->client->get($this->accountPath("contacts/{$contactId}"));

        return Contact::from($this->unwrap($response));
    }

    public function findBySourceId(int $inboxId, string $sourceId): ?Contact
    {
        $response = $this->client->get($this->accountPath('contacts/search'), [
            'q'       => $sourceId,
            'include' => 'contact_inboxes',
            'page'    => 1,
        ]);

        $payload = $response['payload'] ?? [];
        if (! is_array($payload)) {
            return null;
        }

        foreach ($this->arrayOfArrays($payload) as $row) {
            $contact = Contact::from($row);
            if ($contact->sourceIdFor($inboxId) === $sourceId) {
                return $contact;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $additional
     * @param array<string, mixed> $custom
     */
    public function create(
        int $inboxId,
        string $sourceId,
        ?string $name = null,
        ?string $email = null,
        ?string $phoneNumber = null,
        ?string $identifier = null,
        array $additional = [],
        array $custom = [],
    ): Contact {
        $payload = [
            'inbox_id'              => $inboxId,
            'source_id'             => $sourceId,
            'name'                  => $name,
            'email'                 => $email,
            'phone_number'          => $phoneNumber,
            'identifier'            => $identifier,
            'additional_attributes' => (object) $additional,
            'custom_attributes'     => (object) $custom,
        ];

        $response = $this->client->post($this->accountPath('contacts'), array_filter(
            $payload,
            static fn (mixed $v): bool => $v !== null,
        ));

        return Contact::from($this->unwrap($response));
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function update(int $contactId, array $attributes): Contact
    {
        $response = $this->client->patch($this->accountPath("contacts/{$contactId}"), $attributes);

        return Contact::from($this->unwrap($response));
    }

    public function findOrCreate(
        int $inboxId,
        string $sourceId,
        ?string $name = null,
        ?string $phoneNumber = null,
    ): Contact {
        $existing = $this->findBySourceId($inboxId, $sourceId);
        if ($existing !== null) {
            return $existing;
        }

        return $this->create(
            inboxId: $inboxId,
            sourceId: $sourceId,
            name: $name,
            phoneNumber: $phoneNumber,
        );
    }

    /**
     * @param array<string, mixed> $response
     *
     * @return array<string, mixed>
     */
    private function unwrap(array $response): array
    {
        $payload = $response['payload'] ?? null;
        if (is_array($payload) && isset($payload['contact']) && is_array($payload['contact'])) {
            return $payload['contact'];
        }

        if (is_array($payload)) {
            return $payload;
        }

        return $response;
    }
}
