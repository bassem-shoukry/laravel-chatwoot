<?php

namespace BassamShoukry\LaravelChatwoot\Services;

use BassamShoukry\LaravelChatwoot\Events\ContactCreated;
use BassamShoukry\LaravelChatwoot\Events\ContactUpdated;
use BassamShoukry\LaravelChatwoot\Events\ConversationCreated;
use BassamShoukry\LaravelChatwoot\Events\ConversationStatusChanged;
use BassamShoukry\LaravelChatwoot\Events\ConversationUpdated;
use BassamShoukry\LaravelChatwoot\Events\MessageCreated;
use BassamShoukry\LaravelChatwoot\Events\MessageUpdated;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class WebhookHandler
{
    protected array $config;
    protected AccountManager $accountManager;
    protected array $supportedEvents = [
        'conversation_created',
        'conversation_updated',
        'conversation_status_changed',
        'message_created',
        'message_updated',
        'contact_created',
        'contact_updated',
    ];

    public function __construct(AccountManager $accountManager)
    {
        $this->accountManager = $accountManager;
        $this->config = config('chatwoot.webhooks', []);
    }

    /**
     * Handle incoming webhook request.
     */
    public function handle(Request $request): array
    {
        try {
            // Verify webhook signature if configured
            if (! $this->verifySignature($request)) {
                throw new InvalidArgumentException('Invalid webhook signature');
            }

            // Get webhook payload
            $payload = $request->json()->all();
            $event = $payload['event'] ?? null;

            if (! $event) {
                throw new InvalidArgumentException('Missing event in webhook payload');
            }

            // Validate event type
            if (! in_array($event, $this->supportedEvents)) {
                return [
                    'success'          => false,
                    'error'            => "Unsupported event type: $event",
                    'supported_events' => $this->supportedEvents,
                ];
            }

            // Store webhook event in database
            $this->storeWebhookEvent($request, $payload, $event);

            // Process the event
            $result = $this->processEvent($event, $payload);

            return [
                'success'      => true,
                'event'        => $event,
                'result'       => $result,
                'processed_at' => now()->toISOString(),
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error'   => $e->getMessage(),
                'event'   => $payload['event'] ?? null,
            ];
        }
    }

    /**
     * Process specific webhook event.
     */
    protected function processEvent(string $event, array $payload): array
    {
        $result = match ($event) {
            'conversation_created'        => $this->handleConversationCreated($payload),
            'conversation_updated'        => $this->handleConversationUpdated($payload),
            'conversation_status_changed' => $this->handleConversationStatusChanged($payload),
            'message_created'             => $this->handleMessageCreated($payload),
            'message_updated'             => $this->handleMessageUpdated($payload),
            'contact_created'             => $this->handleContactCreated($payload),
            'contact_updated'             => $this->handleContactUpdated($payload),
            default                       => ['processed' => false, 'reason' => 'No handler for event type']
        };

        // Fire Laravel event if configured
        if ($this->config['fire_events'] ?? true) {
            $this->fireEvent($event, $payload);
        }

        return $result;
    }

    /**
     * Handle conversation created event.
     */
    protected function handleConversationCreated(array $payload): array
    {
        $conversation = $payload['conversation'] ?? [];
        $account = $payload['account'] ?? [];

        // Update local conversation tracking if enabled
        if ($this->config['track_conversations'] ?? true) {
            $this->updateConversationRecord($conversation, $account, 'created');
        }

        return [
            'processed'       => true,
            'conversation_id' => $conversation['id'] ?? null,
            'account_id'      => $account['id'] ?? null,
            'status'          => $conversation['status'] ?? null,
        ];
    }

    /**
     * Handle conversation updated event.
     */
    protected function handleConversationUpdated(array $payload): array
    {
        $conversation = $payload['conversation'] ?? [];
        $account = $payload['account'] ?? [];

        if ($this->config['track_conversations'] ?? true) {
            $this->updateConversationRecord($conversation, $account, 'updated');
        }

        return [
            'processed'       => true,
            'conversation_id' => $conversation['id'] ?? null,
            'changes'         => $payload['changes'] ?? [],
        ];
    }

    /**
     * Handle conversation status changed event.
     */
    protected function handleConversationStatusChanged(array $payload): array
    {
        $conversation = $payload['conversation'] ?? [];
        $account = $payload['account'] ?? [];
        $oldStatus = $payload['meta']['old_status'] ?? null;
        $newStatus = $conversation['status'] ?? null;

        if ($this->config['track_conversations'] ?? true) {
            $this->updateConversationRecord($conversation, $account, 'status_changed', [
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
            ]);
        }

        return [
            'processed'       => true,
            'conversation_id' => $conversation['id'] ?? null,
            'old_status'      => $oldStatus,
            'new_status'      => $newStatus,
        ];
    }

    /**
     * Handle message created event.
     */
    protected function handleMessageCreated(array $payload): array
    {
        $message = $payload['message'] ?? [];
        $conversation = $payload['conversation'] ?? [];
        $account = $payload['account'] ?? [];

        // Update message tracking if enabled
        if ($this->config['track_messages'] ?? true) {
            $this->updateMessageRecord($message, $conversation, $account, 'created');
        }

        // Check if this is an incoming message that needs auto-reply
        if ($this->shouldAutoReply($message, $conversation)) {
            $this->handleAutoReply($message, $conversation, $account);
        }

        return [
            'processed'       => true,
            'message_id'      => $message['id'] ?? null,
            'conversation_id' => $conversation['id'] ?? null,
            'message_type'    => $message['message_type'] ?? null,
            'sender_type'     => $message['sender']['type'] ?? null,
        ];
    }

    /**
     * Handle message updated event.
     */
    protected function handleMessageUpdated(array $payload): array
    {
        $message = $payload['message'] ?? [];
        $conversation = $payload['conversation'] ?? [];
        $account = $payload['account'] ?? [];

        if ($this->config['track_messages'] ?? true) {
            $this->updateMessageRecord($message, $conversation, $account, 'updated');
        }

        return [
            'processed'  => true,
            'message_id' => $message['id'] ?? null,
            'changes'    => $payload['changes'] ?? [],
        ];
    }

    /**
     * Handle contact created event.
     */
    protected function handleContactCreated(array $payload): array
    {
        $contact = $payload['contact'] ?? [];
        $account = $payload['account'] ?? [];

        if ($this->config['track_contacts'] ?? true) {
            $this->updateContactRecord($contact, $account, 'created');
        }

        return [
            'processed'     => true,
            'contact_id'    => $contact['id'] ?? null,
            'contact_email' => $contact['email'] ?? null,
        ];
    }

    /**
     * Handle contact updated event.
     */
    protected function handleContactUpdated(array $payload): array
    {
        $contact = $payload['contact'] ?? [];
        $account = $payload['account'] ?? [];

        if ($this->config['track_contacts'] ?? true) {
            $this->updateContactRecord($contact, $account, 'updated');
        }

        return [
            'processed'  => true,
            'contact_id' => $contact['id'] ?? null,
            'changes'    => $payload['changes'] ?? [],
        ];
    }


    /**
     * Verify webhook signature.
     */
    protected function verifySignature(Request $request): bool
    {
        // Skip verification if not configured
        if (! ($this->config['verify_signature'] ?? false)) {
            return true;
        }

        $signature = $request->header('X-Chatwoot-Signature');
        $secret = $this->config['secret'] ?? null;

        if (! $signature || ! $secret) {
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Store webhook event in database.
     */
    protected function storeWebhookEvent(Request $request, array $payload, string $event): void
    {
        if (! DB::getSchemaBuilder()->hasTable('chatwoot_webhook_events')) {
            return;
        }

        DB::table('chatwoot_webhook_events')->insert([
            'event_type'      => $event,
            'account_id'      => $payload['account']['id'] ?? null,
            'conversation_id' => $payload['conversation']['id'] ?? null,
            'message_id'      => $payload['message']['id'] ?? null,
            'contact_id'      => $payload['contact']['id'] ?? null,
            'payload'         => json_encode($payload),
            'headers'         => json_encode($request->headers->all()),
            'processed_at'    => now(),
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
    }

    /**
     * Fire Laravel event.
     */
    protected function fireEvent(string $event, array $payload): void
    {
        $eventClass = match ($event) {
            'conversation_created'        => ConversationCreated::class,
            'conversation_updated'        => ConversationUpdated::class,
            'conversation_status_changed' => ConversationStatusChanged::class,
            'message_created'             => MessageCreated::class,
            'message_updated'             => MessageUpdated::class,
            'contact_created'             => ContactCreated::class,
            'contact_updated'             => ContactUpdated::class,
            default                       => null
        };

        if ($eventClass && class_exists($eventClass)) {
            event(new $eventClass($payload));
        }
    }

    /**
     * Update conversation record in local database.
     */
    protected function updateConversationRecord(array $conversation, array $account, string $action, array $meta = []): void
    {
        if (! DB::getSchemaBuilder()->hasTable('chatwoot_conversations')) {
            return;
        }

        $conversationId = $conversation['id'] ?? null;
        $accountId = $account['id'] ?? null;

        if (! $conversationId || ! $accountId) {
            return;
        }

        $data = [
            'account_id'       => $accountId,
            'conversation_id'  => $conversationId,
            'inbox_id'         => $conversation['inbox_id'] ?? null,
            'contact_id'       => $conversation['meta']['contact']['id'] ?? null,
            'status'           => $conversation['status'] ?? null,
            'assignee_id'      => $conversation['meta']['assignee']['id'] ?? null,
            'team_id'          => $conversation['meta']['team']['id'] ?? null,
            'labels'           => json_encode($conversation['labels'] ?? []),
            'metadata'         => json_encode(array_merge($conversation['meta'] ?? [], $meta)),
            'last_activity_at' => isset($conversation['last_activity_at'])
                ? Carbon::createFromTimestamp($conversation['last_activity_at'])
                : now(),
            'updated_at' => now(),
        ];

        if ($action === 'created') {
            $data['created_at'] = now();
            DB::table('chatwoot_conversations')->updateOrInsert(
                ['conversation_id' => $conversationId, 'account_id' => $accountId],
                $data
            );
        } else {
            DB::table('chatwoot_conversations')
                ->where('conversation_id', $conversationId)
                ->where('account_id', $accountId)
                ->update($data);
        }
    }

    /**
     * Update message record in local database.
     */
    protected function updateMessageRecord(array $message, array $conversation, array $account, string $action): void
    {
        if (! DB::getSchemaBuilder()->hasTable('chatwoot_messages')) {
            return;
        }

        $messageId = $message['id'] ?? null;
        $conversationId = $conversation['id'] ?? null;
        $accountId = $account['id'] ?? null;

        if (! $messageId || ! $conversationId || ! $accountId) {
            return;
        }

        $data = [
            'account_id'      => $accountId,
            'conversation_id' => $conversationId,
            'message_id'      => $messageId,
            'content'         => $message['content'] ?? '',
            'message_type'    => $message['message_type'] ?? 'text',
            'content_type'    => $message['content_type'] ?? null,
            'sender_type'     => $message['sender']['type'] ?? null,
            'sender_id'       => $message['sender']['id'] ?? null,
            'source_id'       => $message['source_id'] ?? null,
            'status'          => 'delivered',
            'metadata'        => json_encode($message),
            'sent_at'         => isset($message['created_at'])
                ? Carbon::createFromTimestamp($message['created_at'])
                : now(),
            'updated_at' => now(),
        ];

        if ($action === 'created') {
            $data['created_at'] = now();
            DB::table('chatwoot_messages')->updateOrInsert(
                ['message_id' => $messageId, 'account_id' => $accountId],
                $data
            );
        } else {
            DB::table('chatwoot_messages')
                ->where('message_id', $messageId)
                ->where('account_id', $accountId)
                ->update($data);
        }
    }

    /**
     * Update contact record in local database.
     */
    protected function updateContactRecord(array $contact, array $account, string $action): void
    {
        if (! DB::getSchemaBuilder()->hasTable('chatwoot_contacts')) {
            return;
        }

        $contactId = $contact['id'] ?? null;
        $accountId = $account['id'] ?? null;

        if (! $contactId || ! $accountId) {
            return;
        }

        $data = [
            'account_id'            => $accountId,
            'contact_id'            => $contactId,
            'name'                  => $contact['name'] ?? null,
            'email'                 => $contact['email'] ?? null,
            'phone_number'          => $contact['phone_number'] ?? null,
            'avatar_url'            => $contact['avatar_url'] ?? null,
            'identifier'            => $contact['identifier'] ?? null,
            'custom_attributes'     => json_encode($contact['custom_attributes'] ?? []),
            'additional_attributes' => json_encode($contact['additional_attributes'] ?? []),
            'updated_at'            => now(),
        ];

        if ($action === 'created') {
            $data['created_at'] = now();
            DB::table('chatwoot_contacts')->updateOrInsert(
                ['contact_id' => $contactId, 'account_id' => $accountId],
                $data
            );
        } else {
            DB::table('chatwoot_contacts')
                ->where('contact_id', $contactId)
                ->where('account_id', $accountId)
                ->update($data);
        }
    }

    /**
     * Check if auto-reply should be triggered.
     */
    protected function shouldAutoReply(array $message, array $conversation): bool
    {
        // Don't auto-reply to outgoing messages or agent messages
        if (($message['sender']['type'] ?? null) === 'agent_bot' ||
            ($message['message_type'] ?? null) === 'outgoing') {
            return false;
        }

        // Check if auto-reply is enabled for this inbox
        $inboxId = $conversation['inbox_id'] ?? null;
        $autoReplyConfig = $this->config['auto_reply'] ?? [];

        return $autoReplyConfig['enabled'] ?? false;
    }

    /**
     * Handle auto-reply logic.
     */
    protected function handleAutoReply(array $message, array $conversation, array $account): void
    {
        $autoReplyConfig = $this->config['auto_reply'] ?? [];

        if (! isset($autoReplyConfig['template'])) {
            return;
        }

        // Queue auto-reply message
        $messageService = app(MessageService::class);

        // Set account context
        $accountKey = $this->findAccountKeyById($account['id'] ?? null);
        if ($accountKey) {
            $this->accountManager->account($accountKey);
        }

        try {
            $result = $messageService->sendTemplate(
                $autoReplyConfig['template'],
                $autoReplyConfig['variables'] ?? [],
                [
                    'conversation_id' => $conversation['id'] ?? null,
                    'delay'           => $autoReplyConfig['delay'] ?? 0,
                ]
            );

        } catch (\Exception $e) {
            // Continue silently on auto-reply failure
        }
    }

    /**
     * Find account key by account ID.
     */
    protected function findAccountKeyById(int $accountId): ?string
    {
        $accounts = config('chatwoot.accounts', []);

        foreach ($accounts as $key => $accountConfig) {
            if (($accountConfig['account_id'] ?? null) == $accountId) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Get webhook statistics.
     */
    public function getStatistics(?string $accountId = null, int $days = 30): array
    {
        if (! DB::getSchemaBuilder()->hasTable('chatwoot_webhook_events')) {
            return ['error' => 'Webhook events table not found'];
        }

        $query = DB::table('chatwoot_webhook_events')
            ->where('created_at', '>=', now()->subDays($days));

        if ($accountId) {
            $query->where('account_id', $accountId);
        }

        $events = $query->get();

        $stats = [
            'total_events' => $events->count(),
            'by_type'      => $events->groupBy('event_type')->map->count(),
            'by_account'   => $events->groupBy('account_id')->map->count(),
            'by_day'       => $events->groupBy(function ($event) {
                return Carbon::parse($event->created_at)->format('Y-m-d');
            })->map->count(),
            'average_per_day' => $events->count() / max($days, 1),
            'period_days'     => $days,
            'account_filter'  => $accountId,
        ];

        return $stats;
    }
}
