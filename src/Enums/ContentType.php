<?php

declare(strict_types=1);

namespace BassamShoukry\LaravelChatwoot\Enums;

enum ContentType: string
{
    case Text = 'text';
    case InputSelect = 'input_select';
    case Cards = 'cards';
    case Form = 'form';
    case Article = 'article';
    case IncomingEmail = 'incoming_email';
    case InputCsat = 'input_csat';
}
