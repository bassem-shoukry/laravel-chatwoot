<?php

declare(strict_types=1);

namespace BassamShoukry\LaravelChatwoot\Events;

use BassamShoukry\LaravelChatwoot\Data\Contact;

final readonly class ContactUpdated
{
    public function __construct(
        public string $accountName,
        public Contact $contact,
        /** @var array<string, mixed> */
        public array $rawPayload,
    ) {}
}
