<?php

declare(strict_types=1);

namespace BassamShoukry\LaravelChatwoot\Enums;

enum EventType: string
{
    case ConversationCreated = 'conversation_created';
    case ConversationUpdated = 'conversation_updated';
    case ConversationStatusChanged = 'conversation_status_changed';
    case ConversationResolved = 'conversation_resolved';
    case ConversationOpened = 'conversation_opened';
    case ConversationTypingOn = 'conversation_typing_on';
    case ConversationTypingOff = 'conversation_typing_off';
    case MessageCreated = 'message_created';
    case MessageUpdated = 'message_updated';
    case ContactCreated = 'contact_created';
    case ContactUpdated = 'contact_updated';
    case WebhookVerification = 'webhook_verification';

    public static function tryFromString(?string $value): ?self
    {
        if ($value === null || $value === '') {
            return null;
        }

        return self::tryFrom($value);
    }
}
