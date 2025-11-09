<?php

namespace BassamShoukry\LaravelChatwoot\Jobs;

use BassamShoukry\LaravelChatwoot\Services\AccountManager;
use BassamShoukry\LaravelChatwoot\Services\InboxManager;
use BassamShoukry\LaravelChatwoot\Services\MessageService;
use BassamShoukry\LaravelChatwoot\Services\RateLimitService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class SendMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries;
    public int $backoff;
    public int $timeout = 300;

    public function __construct(
        protected string $accountKey,
        protected string $inboxKey,
        protected array $messageData,
        protected ?string $templateKey = null
    ) {
        $config = config('chatwoot.queue', []);
        $this->tries = $config['retry_attempts'] ?? 3;
        $this->backoff = $config['retry_delay'] ?? 60;

        // Set queue name and connection
        $this->onQueue($config['queue'] ?? 'chatwoot');
        $this->onConnection($config['connection'] ?? 'default');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Initialize services
            $accountManager = app(AccountManager::class);
            $inboxManager = app(InboxManager::class);
            $messageService = app(MessageService::class);
            $rateLimitService = app(RateLimitService::class);

            // Set account and inbox context
            $accountManager->account($this->accountKey);
            $inboxManager->inbox($this->inboxKey);

            // Check rate limits before processing
            if (! $rateLimitService->checkLimit($this->accountKey, $this->inboxKey)) {
                $delay = $rateLimitService->getDelayUntilReset($this->accountKey, $this->inboxKey);
                $maxDelay = config('chatwoot.queue.rate_limit_delay', 300);

                if ($delay > $maxDelay) {
                    $this->fail('Rate limit delay exceeds maximum allowed time');

                    return;
                }

                $this->release($delay);

                return;
            }

            // Send the message
            if ($this->templateKey) {
                $result = $messageService->sendTemplate(
                    $this->templateKey,
                    $this->messageData['variables'] ?? [],
                    $this->messageData
                );
            } else {
                $result = $messageService->sendMessage($this->messageData);
            }

            if (! $result['success']) {
                throw new \RuntimeException($result['error']);
            }

            // Update message status in database
            $this->updateMessageStatus('sent', $result);

        } catch (\Exception $e) {
            // Update message status
            $this->updateMessageStatus('failed', null, $e->getMessage());

            throw $e; // Re-throw to trigger retry mechanism
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        // Update message status to failed
        $this->updateMessageStatus('failed', null, $exception->getMessage());

        // You could add notification logic here
        // NotificationService::notifyAdministrators($exception, $this->messageData);
    }

    /**
     * Update message status in database.
     */
    protected function updateMessageStatus(string $status, ?array $result = null, ?string $errorMessage = null): void
    {
        if (! DB::getSchemaBuilder()->hasTable('chatwoot_messages')) {
            return;
        }

        try {
            $updateData = [
                'status'      => $status,
                'retry_count' => $this->attempts(),
                'updated_at'  => now(),
            ];

            if ($status === 'sent' && $result) {
                $updateData['sent_at'] = now();
                $updateData['metadata'] = json_encode(array_merge(
                    json_decode($this->getMessageRecord()['metadata'] ?? '{}', true),
                    ['api_response' => $result]
                ));
            }

            if ($errorMessage) {
                $updateData['error_message'] = $errorMessage;
            }

            // Try to find and update the message record
            // This is a simple approach - in production you might want to store job IDs
            DB::table('chatwoot_messages')
                ->where('account_key', $this->accountKey)
                ->where('inbox_key', $this->inboxKey)
                ->where('content', $this->messageData['content'] ?? '')
                ->where('status', 'pending')
                ->orderBy('created_at', 'desc')
                ->limit(1)
                ->update($updateData);

        } catch (\Exception $e) {
            // Continue silently on status update failure
        }
    }

    /**
     * Get message record from database.
     */
    protected function getMessageRecord(): array
    {
        if (! DB::getSchemaBuilder()->hasTable('chatwoot_messages')) {
            return [];
        }

        $record = DB::table('chatwoot_messages')
            ->where('account_key', $this->accountKey)
            ->where('inbox_key', $this->inboxKey)
            ->where('content', $this->messageData['content'] ?? '')
            ->orderBy('created_at', 'desc')
            ->first();

        return $record ? (array) $record : [];
    }

    /**
     * Get unique tags for this job.
     */
    public function tags(): array
    {
        return [
            'chatwoot',
            'send-message',
            "account:{$this->accountKey}",
            "inbox:{$this->inboxKey}",
            $this->templateKey ? "template:{$this->templateKey}" : 'direct-message',
        ];
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        // Exponential backoff: 60s, 180s, 540s
        return [60, 180, 540];
    }

    /**
     * Determine the time at which the job should timeout.
     */
    public function retryAfter(): int
    {
        return 300; // 5 minutes
    }

    /**
     * Get the middleware the job should pass through.
     */
    public function middleware(): array
    {
        return [
            // You could add rate limiting middleware here
            // new \App\Jobs\Middleware\RateLimited($this->accountKey, $this->inboxKey),
        ];
    }
}
