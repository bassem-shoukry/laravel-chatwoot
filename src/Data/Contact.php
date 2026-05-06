<?php

declare(strict_types=1);

namespace BassamShoukry\LaravelChatwoot\Data;

use BassamShoukry\LaravelChatwoot\Data\Concerns\InteractsWithArrays;

final readonly class Contact
{
    use InteractsWithArrays;

    /**
     * @param array<int, array<string, mixed>> $contactInboxes
     * @param array<string, mixed>             $additionalAttributes
     * @param array<string, mixed>             $customAttributes
     * @param array<string, mixed>             $raw
     */
    public function __construct(
        public int $id,
        public ?string $name,
        public ?string $email,
        public ?string $phoneNumber,
        public ?string $identifier,
        public ?string $avatarUrl,
        public array $contactInboxes = [],
        public array $additionalAttributes = [],
        public array $customAttributes = [],
        public array $raw = [],
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function from(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            name: self::string($data, 'name'),
            email: self::string($data, 'email'),
            phoneNumber: self::string($data, 'phone_number'),
            identifier: self::string($data, 'identifier'),
            avatarUrl: self::string($data, 'avatar_url') ?? self::string($data, 'thumbnail'),
            contactInboxes: self::arr($data, 'contact_inboxes') ?? [],
            additionalAttributes: self::arr($data, 'additional_attributes') ?? [],
            customAttributes: self::arr($data, 'custom_attributes') ?? [],
            raw: $data,
        );
    }

    public function sourceIdFor(int $inboxId): ?string
    {
        foreach ($this->contactInboxes as $entry) {
            $entryInbox = (int) ($entry['inbox']['id'] ?? $entry['inbox_id'] ?? 0);
            if ($entryInbox === $inboxId) {
                $sourceId = $entry['source_id'] ?? null;

                return $sourceId === null ? null : (string) $sourceId;
            }
        }

        return null;
    }
}
