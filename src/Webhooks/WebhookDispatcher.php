<?php

declare(strict_types=1);

namespace BassamShoukry\LaravelChatwoot\Webhooks;

use BassamShoukry\LaravelChatwoot\Data\Contact;
use BassamShoukry\LaravelChatwoot\Data\Conversation;
use BassamShoukry\LaravelChatwoot\Data\Message;
use BassamShoukry\LaravelChatwoot\Data\WebhookPayload;
use BassamShoukry\LaravelChatwoot\Enums\ConversationStatus;
use BassamShoukry\LaravelChatwoot\Enums\EventType;
use BassamShoukry\LaravelChatwoot\Events\ContactCreated;
use BassamShoukry\LaravelChatwoot\Events\ContactUpdated;
use BassamShoukry\LaravelChatwoot\Events\ConversationCreated;
use BassamShoukry\LaravelChatwoot\Events\ConversationStatusChanged;
use BassamShoukry\LaravelChatwoot\Events\ConversationUpdated;
use BassamShoukry\LaravelChatwoot\Events\MessageCreated;
use BassamShoukry\LaravelChatwoot\Events\MessageUpdated;
use BassamShoukry\LaravelChatwoot\Events\WebhookReceived;
use Illuminate\Contracts\Events\Dispatcher;

final class WebhookDispatcher
{
    public function __construct(private readonly Dispatcher $events) {}

    public function dispatch(string $accountName, WebhookPayload $payload): void
    {
        $this->events->dispatch(new WebhookReceived($accountName, $payload));

        match ($payload->event) {
            EventType::MessageCreated            => $this->events->dispatch(new MessageCreated($accountName, Message::from($payload->data), $payload->data)),
            EventType::MessageUpdated            => $this->events->dispatch(new MessageUpdated($accountName, Message::from($payload->data), $payload->data)),
            EventType::ConversationCreated       => $this->events->dispatch(new ConversationCreated($accountName, Conversation::from($payload->data), $payload->data)),
            EventType::ConversationUpdated       => $this->events->dispatch(new ConversationUpdated($accountName, Conversation::from($payload->data), $payload->data)),
            EventType::ConversationStatusChanged => $this->events->dispatch(new ConversationStatusChanged(
                $accountName,
                Conversation::from($payload->data),
                ConversationStatus::fromValue((string) ($payload->data['status'] ?? 'open')),
                $payload->data,
            )),
            EventType::ContactCreated => $this->events->dispatch(new ContactCreated($accountName, Contact::from($payload->data), $payload->data)),
            EventType::ContactUpdated => $this->events->dispatch(new ContactUpdated($accountName, Contact::from($payload->data), $payload->data)),
            default                   => null,
        };
    }
}
