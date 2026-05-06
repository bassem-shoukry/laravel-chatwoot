<?php

declare(strict_types=1);

namespace BassamShoukry\LaravelChatwoot\Data;

use BassamShoukry\LaravelChatwoot\Data\Concerns\InteractsWithArrays;
use BassamShoukry\LaravelChatwoot\Enums\ConversationStatus;

final readonly class Conversation
{
    use InteractsWithArrays;

    /**
     * @param array<int, string>   $labels
     * @param array<string, mixed> $additionalAttributes
     * @param array<string, mixed> $customAttributes
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public int $id,
        public ?int $accountId,
        public ?int $inboxId,
        public ?int $contactId,
        public ConversationStatus $status,
        public ?int $assigneeId,
        public ?int $teamId,
        public array $labels = [],
        public array $additionalAttributes = [],
        public array $customAttributes = [],
        public array $raw = [],
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function from(array $data): self
    {
        $contactInbox = $data['contact_inbox'] ?? [];
        $meta = $data['meta'] ?? [];
        $sender = is_array($meta) ? ($meta['sender'] ?? []) : [];
        $assignee = is_array($meta) ? ($meta['assignee'] ?? []) : [];
        $team = is_array($meta) ? ($meta['team'] ?? []) : [];

        $labels = $data['labels'] ?? [];
        $labelsArr = is_array($labels) ? array_map(fn ($v): string => (string) $v, array_values($labels)) : [];

        return new self(
            id: (int) ($data['id'] ?? 0),
            accountId: self::int($data, 'account_id'),
            inboxId: self::int($data, 'inbox_id') ?? (is_array($contactInbox) ? self::int($contactInbox, 'inbox_id') : null),
            contactId: self::int($data, 'contact_id') ?? (is_array($sender) ? self::int($sender, 'id') : null),
            status: ConversationStatus::fromValue(self::string($data, 'status')),
            assigneeId: is_array($assignee) ? self::int($assignee, 'id') : null,
            teamId: is_array($team) ? self::int($team, 'id') : null,
            labels: $labelsArr,
            additionalAttributes: self::arr($data, 'additional_attributes') ?? [],
            customAttributes: self::arr($data, 'custom_attributes') ?? [],
            raw: $data,
        );
    }
}
