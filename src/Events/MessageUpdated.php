<?php

declare(strict_types=1);

namespace BassamShoukry\LaravelChatwoot\Events;

use BassamShoukry\LaravelChatwoot\Data\Message;

final readonly class MessageUpdated
{
    public function __construct(
        public string $accountName,
        public Message $message,
        /** @var array<string, mixed> */
        public array $rawPayload,
    ) {}
}
