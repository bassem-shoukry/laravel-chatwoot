<?php

declare(strict_types=1);

namespace BassamShoukry\LaravelChatwoot\Events;

use BassamShoukry\LaravelChatwoot\Data\Conversation;

final readonly class ConversationUpdated
{
    public function __construct(
        public string $accountName,
        public Conversation $conversation,
        /** @var array<string, mixed> */
        public array $rawPayload,
    ) {}
}
