<?php

declare(strict_types=1);

namespace BassamShoukry\LaravelChatwoot\Enums;

enum MessageType: int
{
    case Incoming = 0;
    case Outgoing = 1;
    case Activity = 2;
    case Template = 3;

    public static function fromValue(int|string|null $value): self
    {
        if ($value === null) {
            return self::Outgoing;
        }

        if (is_string($value)) {
            return match (strtolower($value)) {
                'incoming' => self::Incoming,
                'outgoing' => self::Outgoing,
                'activity' => self::Activity,
                'template' => self::Template,
                default    => self::Outgoing,
            };
        }

        return self::tryFrom($value) ?? self::Outgoing;
    }
}
