<?php

namespace BassamShoukry\LaravelChatwoot;

use BassamShoukry\LaravelChatwoot\Services\AccountManager;
use BassamShoukry\LaravelChatwoot\Services\InboxManager;
use BassamShoukry\LaravelChatwoot\Services\MessageService;
use BassamShoukry\LaravelChatwoot\Services\RateLimitService;
use BassamShoukry\LaravelChatwoot\Services\TemplateService;
use BassamShoukry\LaravelChatwoot\Services\WebhookHandler;

class LaravelChatwoot
{
    protected AccountManager $accountManager;
    protected InboxManager $inboxManager;
    protected MessageService $messageService;
    protected TemplateService $templateService;
    protected RateLimitService $rateLimitService;
    protected WebhookHandler $webhookHandler;

    public function __construct(
        ?AccountManager $accountManager = null,
        ?InboxManager $inboxManager = null,
        ?MessageService $messageService = null,
        ?TemplateService $templateService = null,
        ?RateLimitService $rateLimitService = null,
        ?WebhookHandler $webhookHandler = null
    ) {
        $this->accountManager = $accountManager ?? app(AccountManager::class);
        $this->inboxManager = $inboxManager ?? app(InboxManager::class);
        $this->messageService = $messageService ?? app(MessageService::class);
        $this->templateService = $templateService ?? app(TemplateService::class);
        $this->rateLimitService = $rateLimitService ?? app(RateLimitService::class);
        $this->webhookHandler = $webhookHandler ?? app(WebhookHandler::class);
    }

    /**
     * Select account for subsequent operations.
     *
     * @param string $accountKey Account identifier from config
     *
     * @return $this
     */
    public function account(string $accountKey): self
    {
        $this->accountManager->account($accountKey);

        return $this;
    }

    /**
     * Select inbox for subsequent operations.
     *
     * @param string $inboxKey Inbox identifier from account config
     *
     * @return $this
     */
    public function inbox(string $inboxKey): self
    {
        $this->inboxManager->inbox($inboxKey);

        return $this;
    }

    /**
     * Send a direct message.
     *
     * @param array $messageData Message data including content, contact info, etc.
     */
    public function sendMessage(array $messageData): array
    {
        return $this->messageService->sendMessage($messageData);
    }

    /**
     * Send a template message.
     *
     * @param string $templateKey Template identifier
     * @param array  $variables   Variables to substitute in template
     * @param array  $options     Additional options for the message
     */
    public function sendTemplate(string $templateKey, array $variables = [], array $options = []): array
    {
        return $this->messageService->sendTemplate($templateKey, $variables, $options);
    }

    /**
     * Send bulk messages.
     *
     * @param array $messages Array of message data
     */
    public function sendBulkMessages(array $messages): array
    {
        return $this->messageService->sendBulkMessages($messages);
    }

    /**
     * Queue a message for asynchronous sending.
     *
     * @param array $messageData Message data
     * @param int   $delay       Delay in seconds before sending
     *
     * @return string Job ID
     */
    public function queueMessage(array $messageData, int $delay = 0): string
    {
        return $this->messageService->queueMessage($messageData, $delay);
    }

    /**
     * Queue a template message for asynchronous sending.
     *
     * @param string $templateKey Template identifier
     * @param array  $variables   Variables to substitute
     * @param array  $options     Additional options
     * @param int    $delay       Delay in seconds before sending
     *
     * @return string Job ID
     */
    public function queueTemplate(string $templateKey, array $variables = [], array $options = [], int $delay = 0): string
    {
        return $this->messageService->queueTemplate($templateKey, $variables, $options, $delay);
    }

    /**
     * Queue bulk messages for asynchronous processing.
     *
     * @param array    $messages  Array of messages to send
     * @param int|null $batchSize Number of messages per batch
     *
     * @return string Job ID
     */
    public function queueBulkMessages(array $messages, ?int $batchSize = null): string
    {
        return $this->messageService->queueBulkMessages($messages, $batchSize);
    }

    /**
     * Create a conversation.
     *
     * @param array $contactData Contact information
     */
    public function createConversation(array $contactData): array
    {
        return $this->messageService->createConversation($contactData);
    }

    /**
     * Get conversation details.
     *
     * @param int $conversationId Conversation ID
     */
    public function getConversation(int $conversationId): array
    {
        return $this->messageService->getConversation($conversationId);
    }

    /**
     * Get messages for a conversation.
     *
     * @param int $conversationId Conversation ID
     *
     * @throws \BassamShoukry\LaravelChatwoot\Exceptions\ChatwootApiException
     */
    public function getConversationMessages(int $conversationId): array
    {
        return $this->messageService->getConversationMessages($conversationId);
    }

    /**
     * Get message sending statistics.
     *
     * @param string|null $accountKey Account to filter by
     * @param string|null $inboxKey   Inbox to filter by
     * @param int         $days       Number of days to look back
     */
    public function getMessageStatistics(?string $accountKey = null, ?string $inboxKey = null, int $days = 30): array
    {
        return $this->messageService->getMessageStatistics($accountKey, $inboxKey, $days);
    }

    /**
     * Get available templates.
     *
     * @param string|null $accountKey Account to filter templates
     * @param string|null $inboxKey   Inbox to filter templates
     */
    public function getTemplates(?string $accountKey = null, ?string $inboxKey = null): array
    {
        return $this->templateService->getAvailableTemplates($accountKey, $inboxKey);
    }

    /**
     * Get a specific template.
     *
     * @param string      $templateKey Template identifier
     * @param string|null $accountKey  Account context
     * @param string|null $inboxKey    Inbox context
     */
    public function getTemplate(string $templateKey, ?string $accountKey = null, ?string $inboxKey = null): array
    {
        return $this->templateService->getTemplate($templateKey, $accountKey, $inboxKey);
    }

    /**
     * Process template with variables.
     *
     * @param string      $templateKey Template identifier
     * @param array       $variables   Variables to substitute
     * @param string|null $accountKey  Account context
     * @param string|null $inboxKey    Inbox context
     */
    public function processTemplate(string $templateKey, array $variables = [], ?string $accountKey = null, ?string $inboxKey = null): array
    {
        return $this->templateService->processTemplate($templateKey, $variables, $accountKey, $inboxKey);
    }

    /**
     * Validate template.
     *
     * @param string      $templateKey Template identifier
     * @param array       $variables   Variables to validate
     * @param string|null $accountKey  Account context
     * @param string|null $inboxKey    Inbox context
     */
    public function validateTemplate(string $templateKey, array $variables = [], ?string $accountKey = null, ?string $inboxKey = null): array
    {
        return $this->templateService->validateTemplate($templateKey, $variables, $accountKey, $inboxKey);
    }

    /**
     * Check rate limits for current account/inbox.
     *
     * @param string|null $accountKey Account to check (uses current if null)
     * @param string|null $inboxKey   Inbox to check (uses current if null)
     */
    public function checkRateLimit(?string $accountKey = null, ?string $inboxKey = null): bool
    {
        $routing = $this->inboxManager->getRoutingInfo();
        $accountKey = $accountKey ?? $routing['account_key'];
        $inboxKey = $inboxKey ?? $routing['inbox_key'];

        return $this->rateLimitService->checkLimit($accountKey, $inboxKey);
    }

    /**
     * Get rate limit information.
     *
     * @param string|null $accountKey Account to check (uses current if null)
     * @param string|null $inboxKey   Inbox to check (uses current if null)
     */
    public function getRateLimitInfo(?string $accountKey = null, ?string $inboxKey = null): array
    {
        $routing = $this->inboxManager->getRoutingInfo();
        $accountKey = $accountKey ?? $routing['account_key'];
        $inboxKey = $inboxKey ?? $routing['inbox_key'];

        return $this->rateLimitService->getRateLimitInfo($accountKey, $inboxKey);
    }

    /**
     * Get webhook statistics.
     *
     * @param string|null $accountId Account to filter webhooks
     * @param int         $days      Number of days to look back
     */
    public function getWebhookStatistics(?string $accountId = null, int $days = 30): array
    {
        return $this->webhookHandler->getStatistics($accountId, $days);
    }

    /**
     * Get current account information.
     */
    public function getCurrentAccount(): array
    {
        return $this->accountManager->getCurrentAccountInfo();
    }

    /**
     * Get current inbox information.
     */
    public function getCurrentInbox(): array
    {
        return $this->inboxManager->getCurrentInboxInfo();
    }

    /**
     * Get routing information for current context.
     */
    public function getRoutingInfo(): array
    {
        return $this->inboxManager->getRoutingInfo();
    }

    /**
     * Test connection to Chatwoot API.
     *
     * @param string|null $accountKey Account to test (uses current if null)
     */
    public function testConnection(?string $accountKey = null): array
    {
        if ($accountKey) {
            $this->account($accountKey);
        }

        return $this->accountManager->testConnection();
    }

    /**
     * Fluent method chaining for account and inbox selection
     * Example: LaravelChatwoot::account('primary')->inbox('support')->sendMessage($data).
     *
     * @return static
     */
    public static function for(string $accountKey, ?string $inboxKey = null): self
    {
        $instance = app(self::class);
        $instance->account($accountKey);

        if ($inboxKey) {
            $instance->inbox($inboxKey);
        }

        return $instance;
    }

    /**
     * Quick send method for simple messages.
     *
     * @param string $content     Message content
     * @param array  $contactData Contact information
     * @param array  $options     Additional options
     */
    public function send(string $content, array $contactData, array $options = []): array
    {
        $messageData = array_merge([
            'content' => $content,
            'contact' => $contactData,
        ], $options);

        return $this->sendMessage($messageData);
    }

    /**
     * Quick template send method.
     *
     * @param string $templateKey Template to use
     * @param array  $variables   Variables for template
     * @param array  $contactData Contact information
     * @param array  $options     Additional options
     */
    public function template(string $templateKey, array $variables = [], array $contactData = [], array $options = []): array
    {
        $mergedOptions = array_merge([
            'contact' => $contactData,
        ], $options);

        return $this->sendTemplate($templateKey, $variables, $mergedOptions);
    }
}
