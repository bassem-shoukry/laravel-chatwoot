<?php

namespace BassamShoukry\LaravelChatwoot\Jobs;

use BassamShoukry\LaravelChatwoot\Services\AccountManager;
use BassamShoukry\LaravelChatwoot\Services\InboxManager;
use BassamShoukry\LaravelChatwoot\Services\RateLimitService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Throwable;

class SendBulkMessagesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 1800; // 30 minutes
    public int $backoff = 300; // 5 minutes
    protected int $processedCount = 0;
    protected int $failedCount = 0;
    protected array $errors = [];

    public function __construct(
        protected string $accountKey,
        protected string $inboxKey,
        protected array $messages,
        protected int $batchSize = 50
    ) {
        $config = config('chatwoot.queue', []);

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
            $rateLimitService = app(RateLimitService::class);

            // Set account and inbox context
            $accountManager->account($this->accountKey);
            $inboxManager->inbox($this->inboxKey);

            // Process messages in batches
            $messageBatches = collect($this->messages)->chunk($this->batchSize);

            foreach ($messageBatches as $batchIndex => $batch) {
                $this->processBatch($batch, $batchIndex + 1, $messageBatches->count(), $rateLimitService);

                // Add delay between batches to respect rate limits
                if ($batchIndex < $messageBatches->count() - 1) {
                    $this->delayBetweenBatches($rateLimitService);
                }
            }

            $this->logCompletion();

        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Process a batch of messages.
     */
    protected function processBatch(Collection $batch, int $batchNumber, int $totalBatches, RateLimitService $rateLimitService): void
    {
        foreach ($batch as $messageIndex => $messageData) {
            try {
                // Check rate limits before each message
                if (! $rateLimitService->checkLimit($this->accountKey, $this->inboxKey)) {
                    $this->waitForRateLimit($rateLimitService);
                }

                $this->processMessage($messageData, $batchNumber, $messageIndex);
                $this->processedCount++;

                // Small delay between individual messages to avoid overwhelming the API
                usleep(100000); // 100ms

            } catch (\Exception $e) {
                $this->failedCount++;
                $this->errors[] = [
                    'batch'         => $batchNumber,
                    'message_index' => $messageIndex,
                    'error'         => $e->getMessage(),
                    'message_data'  => $messageData,
                ];

                // Continue with next message instead of failing the entire job
                continue;
            }
        }
    }

    /**
     * Process individual message.
     */
    protected function processMessage(array $messageData, int $batchNumber, int $messageIndex): void
    {
        // Dispatch individual SendMessageJob
        $job = new SendMessageJob(
            $this->accountKey,
            $this->inboxKey,
            $messageData,
            $messageData['template_key'] ?? null
        );

        // Add tags to identify this as part of bulk processing
        $job->tags = array_merge($job->tags ?? [], [
            'bulk-processing',
            "bulk-batch:$batchNumber",
            "bulk-parent:{$this->job->getJobId()}",
        ]);

        dispatch($job);
    }

    /**
     * Wait for rate limit to reset.
     */
    protected function waitForRateLimit(RateLimitService $rateLimitService): void
    {
        $delay = $rateLimitService->getDelayUntilReset($this->accountKey, $this->inboxKey);
        $maxWait = config('chatwoot.queue.rate_limit_delay', 300);

        if ($delay > $maxWait) {
            throw new \RuntimeException("Rate limit delay ($delay s) exceeds maximum wait time ($maxWait s)");
        }

        if ($delay > 0) {
            sleep($delay);
        }
    }

    /**
     * Add delay between batches.
     */
    protected function delayBetweenBatches(RateLimitService $rateLimitService): void
    {
        // Get optimal delay based on rate limits
        $rateLimitInfo = $rateLimitService->getRateLimitInfo($this->accountKey, $this->inboxKey);

        // Calculate delay to avoid hitting rate limits
        $delay = 1; // Minimum 1 second delay

        if (isset($rateLimitInfo['per_minute'])) {
            $minuteRemaining = $rateLimitInfo['per_minute']['remaining'];
            if ($minuteRemaining < $this->batchSize) {
                $delay = max($delay, 60); // Wait for minute reset
            }
        }

        if ($delay > 1) {
            sleep($delay);
        }
    }

    /**
     * Log job completion.
     */
    protected function logCompletion(): void
    {
        // Job completion tracking handled internally
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        // You could add notification logic here
        // NotificationService::notifyAdministrators($exception, [
        //     'job_type' => 'bulk_messages',
        //     'account_key' => $this->accountKey,
        //     'inbox_key' => $this->inboxKey,
        //     'processed_count' => $this->processedCount,
        //     'failed_count' => $this->failedCount,
        // ]);
    }

    /**
     * Get unique tags for this job.
     */
    public function tags(): array
    {
        return [
            'chatwoot',
            'bulk-messages',
            "account:{$this->accountKey}",
            "inbox:{$this->inboxKey}",
            "batch-size:{$this->batchSize}",
            'total-messages:' . count($this->messages),
        ];
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        // Progressive backoff for bulk jobs: 5min, 15min, 30min
        return [300, 900, 1800];
    }

    /**
     * Get the middleware the job should pass through.
     */
    public function middleware(): array
    {
        return [
            // You could add bulk processing middleware here
            // new \App\Jobs\Middleware\BulkRateLimited($this->accountKey, $this->inboxKey),
        ];
    }

    /**
     * Get processing statistics.
     */
    public function getStatistics(): array
    {
        return [
            'total_messages'  => count($this->messages),
            'processed_count' => $this->processedCount,
            'failed_count'    => $this->failedCount,
            'error_count'     => count($this->errors),
            'success_rate'    => $this->processedCount > 0
                ? (($this->processedCount / ($this->processedCount + $this->failedCount)) * 100)
                : 0,
            'batch_size'  => $this->batchSize,
            'account_key' => $this->accountKey,
            'inbox_key'   => $this->inboxKey,
        ];
    }
}
