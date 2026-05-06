<?php

declare(strict_types=1);

namespace BassamShoukry\LaravelChatwoot\Enums;

enum ChannelType: string
{
    case Api = 'api';
    case Email = 'email';
    case Facebook = 'facebook';
    case Instagram = 'instagram';
    case Line = 'line';
    case Sms = 'sms';
    case Telegram = 'telegram';
    case Twitter = 'twitter';
    case WebWidget = 'web_widget';
    case Whatsapp = 'whatsapp';

    public static function fromChatwoot(string $value): self
    {
        $value = str_replace('Channel::', '', $value);
        $value = strtolower($value);

        return self::tryFrom($value) ?? self::Api;
    }
}
