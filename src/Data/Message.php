<?php

declare(strict_types=1);

namespace BassamShoukry\LaravelChatwoot\Data;

use BassamShoukry\LaravelChatwoot\Data\Concerns\InteractsWithArrays;
use BassamShoukry\LaravelChatwoot\Enums\ContentType;
use BassamShoukry\LaravelChatwoot\Enums\MessageType;

final readonly class Message
{
    use InteractsWithArrays;

    /**
     * @param array<int, Attachment> $attachments
     * @param array<string, mixed>   $contentAttributes
     * @param array<string, mixed>   $raw
     */
    public function __construct(
        public int $id,
        public ?int $conversationId,
        public ?int $inboxId,
        public ?int $accountId,
        public MessageType $type,
        public ContentType $contentType,
        public ?string $content,
        public ?int $senderId,
        public ?string $senderType,
        public ?int $createdAtUnix,
        public bool $private,
        public array $attachments = [],
        public array $contentAttributes = [],
        public array $raw = [],
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function from(array $data): self
    {
        $sender = $data['sender'] ?? [];
        $attachmentsRaw = is_array($data['attachments'] ?? null) ? $data['attachments'] : [];

        return new self(
            id: (int) ($data['id'] ?? 0),
            conversationId: self::int($data, 'conversation_id'),
            inboxId: self::int($data, 'inbox_id'),
            accountId: self::int($data, 'account_id'),
            type: MessageType::fromValue($data['message_type'] ?? null),
            contentType: ContentType::tryFrom((string) ($data['content_type'] ?? 'text')) ?? ContentType::Text,
            content: self::string($data, 'content'),
            senderId: is_array($sender) ? self::int($sender, 'id') : null,
            senderType: is_array($sender) ? self::string($sender, 'type') : null,
            createdAtUnix: self::int($data, 'created_at'),
            private: (bool) ($data['private'] ?? false),
            attachments: array_map(
                static fn (array $a): Attachment => Attachment::from($a),
                array_values(array_filter($attachmentsRaw, 'is_array')),
            ),
            contentAttributes: self::arr($data, 'content_attributes') ?? [],
            raw: $data,
        );
    }
}
