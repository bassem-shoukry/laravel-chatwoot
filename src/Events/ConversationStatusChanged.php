<?php

declare(strict_types=1);

namespace BassamShoukry\LaravelChatwoot\Events;

use BassamShoukry\LaravelChatwoot\Data\Conversation;
use BassamShoukry\LaravelChatwoot\Enums\ConversationStatus;

final readonly class ConversationStatusChanged
{
    public function __construct(
        public string $accountName,
        public Conversation $conversation,
        public ConversationStatus $status,
        /** @var array<string, mixed> */
        public array $rawPayload,
    ) {}
}
