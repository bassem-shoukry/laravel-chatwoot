<?php

declare(strict_types=1);

namespace BassamShoukry\LaravelChatwoot\Facades;

use BassamShoukry\LaravelChatwoot\ChatwootManager;
use BassamShoukry\LaravelChatwoot\Contracts\ChatwootClient;
use BassamShoukry\LaravelChatwoot\Resources\AgentResource;
use BassamShoukry\LaravelChatwoot\Resources\CannedResponseResource;
use BassamShoukry\LaravelChatwoot\Resources\ContactResource;
use BassamShoukry\LaravelChatwoot\Resources\ConversationResource;
use BassamShoukry\LaravelChatwoot\Resources\InboxResource;
use BassamShoukry\LaravelChatwoot\Resources\LabelResource;
use BassamShoukry\LaravelChatwoot\Resources\MessageResource;
use BassamShoukry\LaravelChatwoot\Resources\TeamResource;
use Illuminate\Support\Facades\Facade;

/**
 * @method static ChatwootManager        account(?string $name = null)
 * @method static ChatwootClient         client()
 * @method static ConversationResource   conversations()
 * @method static MessageResource        messages()
 * @method static ContactResource        contacts()
 * @method static InboxResource          inboxes()
 * @method static AgentResource          agents()
 * @method static TeamResource           teams()
 * @method static LabelResource          labels()
 * @method static CannedResponseResource cannedResponses()
 *
 * @see ChatwootManager
 */
final class Chatwoot extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ChatwootManager::class;
    }
}
