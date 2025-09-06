<?php

namespace BassamShoukry\LaravelChatwoot\Services;

use BassamShoukry\LaravelChatwoot\Exceptions\AccountNotFoundException;
use BassamShoukry\LaravelChatwoot\Exceptions\ChatwootApiException;
use BassamShoukry\LaravelChatwoot\Exceptions\InboxNotFoundException;
use BassamShoukry\LaravelChatwoot\Jobs\SendBulkMessagesJob;
use BassamShoukry\LaravelChatwoot\Jobs\SendMessageJob;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use InvalidArgumentException;
use RuntimeException;

class MessageService
{
    protected AccountManager $accountManager;
    protected InboxManager $inboxManager;
    protected TemplateService $templateService;
    protected ApiClient $apiClient;
    protected ChannelService $channelService;
    protected RateLimitService $rateLimitService;
    protected array $config;

    public function __construct(
        AccountManager $accountManager,
        InboxManager $inboxManager,
        TemplateService $templateService,
        ApiClient $apiClient
    ) {
        $this->accountManager = $accountManager;
        $this->inboxManager = $inboxManager;
        $this->templateService = $templateService;
        $this->apiClient = $apiClient;
        $this->channelService = app(ChannelService::class);
        $this->rateLimitService = app(RateLimitService::class);
        $this->config = config('chatwoot', []);
    }

    /**
     * Send a message using account/inbox context.
     */
    public function sendMessage(array $messageData): array
    {
        try {
            // Get routing information
            $routing = $this->inboxManager->getRoutingInfo();

            // Validate rate limits
            if (! $this->rateLimitService->checkLimit($routing['account_key'], $routing['inbox_key'])) {
                throw new RuntimeException('Rate limit exceeded for account/inbox combination');
            }

            // Validate message data
            $validated = $this->validateMessageData($messageData, $routing);
            if (! $validated['valid']) {
                throw new InvalidArgumentException('Message validation failed: ' . implode(', ', $validated['errors']));
            }

            // Prepare message for API
            $preparedMessage = $this->prepareMessage($messageData, $routing);

            // Create conversation if needed
            if (! isset($preparedMessage['conversation_id'])) {
                $conversation = $this->createConversation($preparedMessage['contact_data'], $routing);
                $preparedMessage['conversation_id'] = $conversation['id'];
            }

            // Send message via API
            $response = $this->apiClient->sendMessage(
                $routing['account_url'],
                $routing['account_token'],
                $routing['inbox_id'],
                $preparedMessage['conversation_id'],
                $preparedMessage['message_data']
            );

            // Increment rate limit counter
            $this->rateLimitService->incrementCounter($routing['account_key'], $routing['inbox_key']);

            // Log the message
            $this->logMessage($preparedMessage, $response, 'sent', $routing);

            return [
                'success'         => true,
                'message_id'      => $response['id'] ?? null,
                'conversation_id' => $preparedMessage['conversation_id'],
                'routing'         => $routing,
                'response'        => $response,
                'sent_at'         => now()->toISOString(),
            ];

        } catch (Exception $e) {
            Log::error('Failed to send message: ' . $e->getMessage(), [
                'message_data' => $messageData,
                'routing'      => $routing ?? null,
            ]);

            // Log the failure
            if (isset($routing)) {
                $this->logMessage($messageData, [], 'failed', $routing, $e->getMessage());
            }

            return [
                'success' => false,
                'error'   => $e->getMessage(),
                'routing' => $routing ?? null,
            ];
        }
    }

    /**
     * Send message using template.
     */
    public function sendTemplate(string $templateKey, array $variables = [], array $options = []): array
    {
        try {
            $routing = $this->inboxManager->getRoutingInfo();

            // Get and process template
            $template = $this->templateService->processTemplate(
                $templateKey,
                $variables,
                $routing['account_key'],
                $routing['inbox_key']
            );

            // Validate template for current context
            $templateValidation = $this->templateService->validateTemplate(
                $templateKey,
                $variables,
                $routing['account_key'],
                $routing['inbox_key']
            );

            if (! $templateValidation['valid']) {
                throw new InvalidArgumentException('Template validation failed: ' . implode(', ', $templateValidation['errors']));
            }

            // Prepare message data from template
            $messageData = array_merge([
                'content'      => $template['content']['text'] ?? '',
                'message_type' => $template['content']['type'] ?? 'text',
                'template_key' => $templateKey,
                'variables'    => $variables,
            ], $options);

            return $this->sendMessage($messageData);

        } catch (Exception $e) {
            Log::error('Failed to send template message: ' . $e->getMessage(), [
                'template_key' => $templateKey,
                'variables'    => $variables,
                'options'      => $options,
            ]);

            return [
                'success'      => false,
                'error'        => $e->getMessage(),
                'template_key' => $templateKey,
            ];
        }
    }

    /**
     * Send bulk messages.
     */
    public function sendBulkMessages(array $messages): array
    {
        $results = [
            'total'      => count($messages),
            'successful' => 0,
            'failed'     => 0,
            'results'    => [],
            'errors'     => [],
        ];

        foreach ($messages as $index => $messageData) {
            try {
                $result = $this->sendMessage($messageData);

                if ($result['success']) {
                    $results['successful']++;
                } else {
                    $results['failed']++;
                    $results['errors'][] = "Message $index: " . $result['error'];
                }

                $results['results'][] = $result;

            } catch (Exception $e) {
                $results['failed']++;
                $results['errors'][] = "Message $index: " . $e->getMessage();
                $results['results'][] = [
                    'success' => false,
                    'error'   => $e->getMessage(),
                    'index'   => $index,
                ];
            }
        }

        Log::info('Bulk messages sent', $results);

        return $results;
    }

    /**
     * Send bulk template messages.
     *
     * @throws AccountNotFoundException
     * @throws InboxNotFoundException
     */
    public function sendBulkTemplates(array $templateMessages): array
    {
        $messages = [];

        foreach ($templateMessages as $templateMessage) {
            $account = $templateMessage['account'] ?? null;
            $inbox = $templateMessage['inbox'] ?? null;
            $templateKey = $templateMessage['template'];
            $variables = $templateMessage['variables'] ?? [];
            $options = $templateMessage['options'] ?? [];

            // Switch context if needed
            if ($account) {
                $this->accountManager->account($account);
            }
            if ($inbox) {
                $this->inboxManager->inbox($inbox);
            }

            try {
                $result = $this->sendTemplate($templateKey, $variables, $options);
                $messages[] = $result;
            } catch (Exception $e) {
                $messages[] = [
                    'success'      => false,
                    'error'        => $e->getMessage(),
                    'template_key' => $templateKey,
                    'account'      => $account,
                    'inbox'        => $inbox,
                ];
            }
        }

        $successful = collect($messages)->where('success', true)->count();
        $failed = collect($messages)->where('success', false)->count();

        return [
            'total'      => count($messages),
            'successful' => $successful,
            'failed'     => $failed,
            'results'    => $messages,
        ];
    }

    /**
     * Queue message for sending.
     */
    public function queueMessage(array $messageData, int $delay = 0): string
    {
        $routing = $this->inboxManager->getRoutingInfo();

        $job = new SendMessageJob(
            $routing['account_key'],
            $routing['inbox_key'],
            $messageData
        );

        if ($delay > 0) {
            $job->delay($delay);
        }

        $jobId = Queue::push($job);

        Log::info('Message queued for sending', [
            'job_id'  => $jobId,
            'routing' => $routing,
            'delay'   => $delay,
        ]);

        return $jobId;
    }

    /**
     * Queue template message for sending.
     */
    public function queueTemplate(string $templateKey, array $variables = [], array $options = [], int $delay = 0): string
    {
        $this->inboxManager->getRoutingInfo();

        $messageData = array_merge([
            'template_key' => $templateKey,
            'variables'    => $variables,
        ], $options);

        return $this->queueMessage($messageData, $delay);
    }

    /**
     * Queue bulk messages for sending.
     */
    public function queueBulkMessages(array $messages, ?int $batchSize = null): string
    {
        $routing = $this->inboxManager->getRoutingInfo();

        if (! $batchSize) {
            $batchSize = $this->rateLimitService->calculateOptimalBatchSize(
                $routing['account_key'],
                $routing['inbox_key']
            );
        }

        $job = new SendBulkMessagesJob(
            $routing['account_key'],
            $routing['inbox_key'],
            $messages,
            $batchSize
        );

        $jobId = Queue::push($job);

        Log::info('Bulk messages queued for sending', [
            'job_id'        => $jobId,
            'message_count' => count($messages),
            'batch_size'    => $batchSize,
            'routing'       => $routing,
        ]);

        return $jobId;
    }

    /**
     * Create a conversation.
     */
    public function createConversation(array $contactData, ?array $routingInfo = null): array
    {
        $routing = $routingInfo ?? $this->inboxManager->getRoutingInfo();

        $conversationData = [
            'source_id'             => $contactData['identifier'] ?? uniqid('contact_'),
            'inbox_id'              => $routing['inbox_id'],
            'contact'               => $contactData,
            'additional_attributes' => $contactData['additional_attributes'] ?? [],
        ];

        $response = $this->apiClient->createConversation(
            $routing['account_url'],
            $routing['account_token'],
            $routing['account_key'],
            $conversationData
        );

        Log::info('Conversation created', [
            'conversation_id' => $response['id'] ?? null,
            'routing'         => $routing,
        ]);

        return $response;
    }

    /**
     * Get conversation details.
     */
    public function getConversation(int $conversationId): array
    {
        $routing = $this->inboxManager->getRoutingInfo();

        return $this->apiClient->getConversation(
            $routing['account_url'],
            $routing['account_token'],
            $routing['account_key'],
            (string) $conversationId
        );
    }

    /**
     * Get messages for a conversation.
     *
     * @throws ChatwootApiException
     */
    public function getConversationMessages(int $conversationId): array
    {
        $routing = $this->inboxManager->getRoutingInfo();

        $url = $this->apiClient->buildUrl(
            $routing['account_url'],
            "api/v1/accounts/{$routing['account_key']}/conversations/$conversationId/messages"
        );

        return $this->apiClient->get($url, $routing['account_token']);
    }

    /**
     * Get message statistics.
     */
    public function getMessageStatistics(?string $accountKey = null, ?string $inboxKey = null, int $days = 30): array
    {
        $query = DB::table('chatwoot_messages');

        if ($accountKey) {
            $query->where('account_key', $accountKey);
        }

        if ($inboxKey) {
            $query->where('inbox_key', $inboxKey);
        }

        $query->where('created_at', '>=', now()->subDays($days));

        $stats = [
            'total_messages' => $query->count(),
            'by_status'      => $query->groupBy('status')
                ->selectRaw('status, count(*) as count')
                ->get()
                ->pluck('count', 'status')
                ->toArray(),
            'by_channel' => $query->groupBy('channel')
                ->selectRaw('channel, count(*) as count')
                ->get()
                ->pluck('count', 'channel')
                ->toArray(),
            'success_rate'        => 0,
            'average_retry_count' => $query->avg('retry_count') ?? 0,
        ];

        $totalSent = ($stats['by_status']['sent'] ?? 0) + ($stats['by_status']['delivered'] ?? 0);
        if ($stats['total_messages'] > 0) {
            $stats['success_rate'] = ($totalSent / $stats['total_messages']) * 100;
        }

        return $stats;
    }

    /**
     * Validate message data.
     */
    protected function validateMessageData(array $messageData, array $routing): array
    {
        $errors = [];

        // Required fields
        if (empty($messageData['content'])) {
            $errors[] = 'Message content is required';
        }

        // Channel validation if specified
        if (isset($messageData['channel'])) {
            $channel = $messageData['channel'];
            $channelValidation = $this->channelService->validateMessage($channel, $messageData);

            if (! $channelValidation['valid']) {
                $errors = array_merge($errors, $channelValidation['errors']);
            }

            // Check if inbox supports the channel
            if (! in_array($channel, $routing['channels'])) {
                $errors[] = "Channel '$channel' is not supported by the selected inbox";
            }
        }

        // Template validation if template is used
        if (isset($messageData['template_key'])) {
            $templateValidation = $this->templateService->validateTemplate(
                $messageData['template_key'],
                $messageData['variables'] ?? [],
                $routing['account_key'],
                $routing['inbox_key']
            );

            if (! $templateValidation['valid']) {
                $errors = array_merge($errors, $templateValidation['errors']);
            }
        }

        return [
            'valid'  => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Prepare message for API call.
     */
    protected function prepareMessage(array $messageData, array $routing): array
    {
        $prepared = [
            'message_data' => [
                'content'      => $messageData['content'],
                'message_type' => $messageData['message_type'] ?? 'text',
            ],
            'contact_data' => $messageData['contact'] ?? [
                'identifier'   => $messageData['contact_identifier'] ?? uniqid('contact_'),
                'name'         => $messageData['contact_name'] ?? 'Unknown',
                'email'        => $messageData['contact_email'] ?? null,
                'phone_number' => $messageData['contact_phone'] ?? null,
            ],
        ];

        // Add conversation ID if provided
        if (isset($messageData['conversation_id'])) {
            $prepared['conversation_id'] = $messageData['conversation_id'];
        }

        // Add attachments if provided
        if (isset($messageData['attachments'])) {
            $prepared['message_data']['attachments'] = $messageData['attachments'];
        }

        // Add metadata
        $prepared['message_data']['metadata'] = array_merge(
            $messageData['metadata'] ?? [],
            [
                'sent_via'    => 'laravel-chatwoot-package',
                'account_key' => $routing['account_key'],
                'inbox_key'   => $routing['inbox_key'],
                'sent_at'     => now()->toISOString(),
            ]
        );

        return $prepared;
    }

    /**
     * Log message to database.
     */
    protected function logMessage(array $messageData, array $response, string $status, array $routing, ?string $errorMessage = null): void
    {
        if (! DB::getSchemaBuilder()->hasTable('chatwoot_messages')) {
            return;
        }

        DB::table('chatwoot_messages')->insert([
            'account_key'     => $routing['account_key'],
            'inbox_key'       => $routing['inbox_key'],
            'template_key'    => $messageData['template_key'] ?? null,
            'conversation_id' => $response['conversation']['id'] ?? $messageData['conversation_id'] ?? null,
            'contact_id'      => $response['sender']['id'] ?? null,
            'channel'         => $messageData['channel'] ?? 'unknown',
            'content'         => is_string($messageData['content']) ? $messageData['content'] : json_encode($messageData['content']),
            'variables'       => json_encode($messageData['variables'] ?? []),
            'metadata'        => json_encode(array_merge(
                $messageData['metadata'] ?? [],
                ['response' => $response]
            )),
            'status'        => $status,
            'error_message' => $errorMessage,
            'retry_count'   => 0,
            'sent_at'       => $status === 'sent' ? now() : null,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }
}
