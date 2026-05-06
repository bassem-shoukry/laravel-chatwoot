<?php

declare(strict_types=1);

namespace BassamShoukry\LaravelChatwoot\Events;

use BassamShoukry\LaravelChatwoot\Data\WebhookPayload;

final readonly class WebhookReceived
{
    public function __construct(
        public string $accountName,
        public WebhookPayload $payload,
    ) {}
}
