<?php

declare(strict_types=1);

namespace BassamShoukry\LaravelChatwoot;

use BassamShoukry\LaravelChatwoot\Contracts\AccountResolver;
use BassamShoukry\LaravelChatwoot\Contracts\ChatwootClient;
use BassamShoukry\LaravelChatwoot\Http\ApiClient;
use BassamShoukry\LaravelChatwoot\Resources\AgentResource;
use BassamShoukry\LaravelChatwoot\Resources\CannedResponseResource;
use BassamShoukry\LaravelChatwoot\Resources\ContactResource;
use BassamShoukry\LaravelChatwoot\Resources\ConversationResource;
use BassamShoukry\LaravelChatwoot\Resources\InboxResource;
use BassamShoukry\LaravelChatwoot\Resources\LabelResource;
use BassamShoukry\LaravelChatwoot\Resources\MessageResource;
use BassamShoukry\LaravelChatwoot\Resources\TeamResource;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\Client\Factory as HttpFactory;
use Psr\Log\LoggerInterface;

final class ChatwootManager
{
    /** @var array<string, ChatwootClient> */
    private array $clients = [];

    private ?string $boundAccount = null;

    public function __construct(
        private readonly AccountResolver $accounts,
        private readonly HttpFactory $http,
        private readonly ConfigRepository $config,
        private readonly LoggerInterface $logger,
    ) {}

    public function account(?string $name = null): self
    {
        $clone = clone $this;
        $clone->boundAccount = $name;

        return $clone;
    }

    public function client(): ChatwootClient
    {
        $context = $this->accounts->resolve($this->boundAccount);

        return $this->clients[$context->name] ??= new ApiClient(
            http: $this->http,
            config: $this->config,
            logger: $this->logger,
            account: $context,
        );
    }

    public function setClient(string $name, ChatwootClient $client): void
    {
        $this->clients[$name] = $client;
    }

    public function conversations(): ConversationResource
    {
        return new ConversationResource($this->client());
    }

    public function messages(): MessageResource
    {
        return new MessageResource($this->client());
    }

    public function contacts(): ContactResource
    {
        return new ContactResource($this->client());
    }

    public function inboxes(): InboxResource
    {
        return new InboxResource($this->client());
    }

    public function agents(): AgentResource
    {
        return new AgentResource($this->client());
    }

    public function teams(): TeamResource
    {
        return new TeamResource($this->client());
    }

    public function labels(): LabelResource
    {
        return new LabelResource($this->client());
    }

    public function cannedResponses(): CannedResponseResource
    {
        return new CannedResponseResource($this->client());
    }
}
