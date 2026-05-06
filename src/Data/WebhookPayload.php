<?php

declare(strict_types=1);

namespace BassamShoukry\LaravelChatwoot\Data;

use BassamShoukry\LaravelChatwoot\Enums\EventType;

final readonly class WebhookPayload
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public ?EventType $event,
        public string $rawEvent,
        public array $data,
    ) {}

    /**
     * @param array<string, mixed> $payload
     */
    public static function from(array $payload): self
    {
        $rawEvent = (string) ($payload['event'] ?? '');

        return new self(
            event: EventType::tryFromString($rawEvent),
            rawEvent: $rawEvent,
            data: $payload,
        );
    }
}
