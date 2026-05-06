<?php

declare(strict_types=1);

namespace BassamShoukry\LaravelChatwoot\Enums;

enum ConversationStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';
    case Pending = 'pending';
    case Snoozed = 'snoozed';

    public static function fromValue(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::Open;
    }
}
