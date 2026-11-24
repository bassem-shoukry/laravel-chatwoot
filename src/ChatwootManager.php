<?php

declare(strict_types=1);

namespace BassamShoukry\LaravelChatwoot;

use ArrayObject;
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
use BassamShoukry\LaravelChatwoot\Support\AccountContext;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\Client\Factory as HttpFactory;
use Psr\Log\LoggerInterface;

final class ChatwootManager
{
    /**
     * Client registry shared by this manager and every account() clone, so a
     * client registered or built on a clone is visible to the root manager.
     *
     * @var ArrayObject<string, ChatwootClient>
     */
    private ArrayObject $clients;

    /**
     * The account context each auto-built client was created for, so a
     * client is rebuilt when its account config changes. Clients registered
     * through setClient() have no entry and are never replaced.
     *
     * @var ArrayObject<string, AccountContext>
     */
    private ArrayObject $builtFor;

    private ?string $boundAccount = null;

    public function __construct(
        private readonly AccountResolver $accounts,
        private readonly HttpFactory $http,
        private readonly ConfigRepository $config,
        private readonly LoggerInterface $logger,
    ) {
        $this->clients = new ArrayObject;
        $this->builtFor = new ArrayObject;
    }

    public function account(?string $name = null): self
    {
        $clone = clone $this;
        $clone->boundAccount = $name;

        return $clone;
    }

    public function client(): ChatwootClient
    {
        // A client registered with setClient() (e.g. ChatwootFake) needs no
        // account config at all, so check it before resolving the account.
        $requested = $this->boundAccount ?? (string) $this->config->get('chatwoot.default_account', 'default');
        if (isset($this->clients[$requested]) && ! isset($this->builtFor[$requested])) {
            return $this->clients[$requested];
        }

        $context = $this->accounts->resolve($this->boundAccount);
        $name = $context->name;

        if (isset($this->clients[$name]) && (! isset($this->builtFor[$name]) || $this->builtFor[$name] === $context)) {
            return $this->clients[$name];
        }

        $this->builtFor[$name] = $context;

        return $this->clients[$name] = new ApiClient(
            http: $this->http,
            config: $this->config,
            logger: $this->logger,
            account: $context,
        );
    }

    public function setClient(string $name, ChatwootClient $client): void
    {
        $this->clients[$name] = $client;
        unset($this->builtFor[$name]);
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
