<?php

namespace BassamShoukry\LaravelChatwoot\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class RateLimitService
{
    protected array $config;
    protected string $cacheStore;
    protected string $cachePrefix;

    public function __construct()
    {
        $this->config = Config::get('chatwoot', []);
        $this->cacheStore = $this->config['cache']['store'] ?? 'default';
        $this->cachePrefix = $this->config['cache']['rate_limits']['prefix'] ?? 'chatwoot_limits';
    }

    /**
     * Check if rate limit allows the operation.
     */
    public function checkLimit(string $accountId, string $inboxId): bool
    {
        $limits = $this->getRateLimits($accountId, $inboxId);

        foreach (['per_minute', 'per_hour', 'per_day'] as $period) {
            if (isset($limits[$period])) {
                $currentCount = $this->getCurrentCount($accountId, $inboxId, $period);
                if ($currentCount >= $limits[$period]) {
                    Log::warning("Rate limit exceeded for $accountId:$inboxId - $period: {$currentCount}/{$limits[$period]}");

                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Increment the counter for rate limiting.
     */
    public function incrementCounter(string $accountId, string $inboxId): void
    {
        $this->incrementPeriodCounter($accountId, $inboxId, 'per_minute', 60);
        $this->incrementPeriodCounter($accountId, $inboxId, 'per_hour', 3600);
        $this->incrementPeriodCounter($accountId, $inboxId, 'per_day', 86400);
    }

    /**
     * Get remaining limit for a specific period.
     */
    public function getRemainingLimit(string $accountId, string $inboxId, string $period = 'per_minute'): int
    {
        $limits = $this->getRateLimits($accountId, $inboxId);
        $maxLimit = $limits[$period] ?? 0;
        $currentCount = $this->getCurrentCount($accountId, $inboxId, $period);

        return max(0, $maxLimit - $currentCount);
    }

    /**
     * Get time when rate limit will reset.
     */
    public function getResetTime(string $accountId, string $inboxId, string $period = 'per_minute'): Carbon
    {
        $now = Carbon::now();

        return match ($period) {
            'per_minute' => $now->copy()->addMinute()->startOfMinute(),
            'per_hour'   => $now->copy()->addHour()->startOfHour(),
            'per_day'    => $now->copy()->addDay()->startOfDay(),
            default      => $now->copy()->addMinute(),
        };
    }

    /**
     * Get all rate limit information.
     */
    public function getRateLimitInfo(string $accountId, string $inboxId): array
    {
        $limits = $this->getRateLimits($accountId, $inboxId);
        $info = [];

        foreach (['per_minute', 'per_hour', 'per_day'] as $period) {
            if (isset($limits[$period])) {
                $currentCount = $this->getCurrentCount($accountId, $inboxId, $period);
                $maxLimit = $limits[$period];

                $info[$period] = [
                    'limit'     => $maxLimit,
                    'used'      => $currentCount,
                    'remaining' => max(0, $maxLimit - $currentCount),
                    'reset_at'  => $this->getResetTime($accountId, $inboxId, $period)->toISOString(),
                    'exceeded'  => $currentCount >= $maxLimit,
                ];
            }
        }

        return $info;
    }

    /**
     * Check if any rate limit is currently exceeded.
     */
    public function isRateLimited(string $accountId, string $inboxId): bool
    {
        return ! $this->checkLimit($accountId, $inboxId);
    }

    /**
     * Get estimated delay until rate limit resets.
     */
    public function getDelayUntilReset(string $accountId, string $inboxId): int
    {
        if (! $this->isRateLimited($accountId, $inboxId)) {
            return 0;
        }

        $limits = $this->getRateLimits($accountId, $inboxId);
        $delays = [];

        foreach (['per_minute', 'per_hour', 'per_day'] as $period) {
            if (isset($limits[$period])) {
                $currentCount = $this->getCurrentCount($accountId, $inboxId, $period);
                if ($currentCount >= $limits[$period]) {
                    $resetTime = $this->getResetTime($accountId, $inboxId, $period);
                    $delays[] = $resetTime->diffInSeconds(Carbon::now());
                }
            }
        }

        return empty($delays) ? 0 : min($delays);
    }

    /**
     * Wait until rate limit allows operation.
     */
    public function waitForRateLimit(string $accountId, string $inboxId, int $maxWaitSeconds = 300): bool
    {
        $delay = $this->getDelayUntilReset($accountId, $inboxId);

        if ($delay === 0) {
            return true; // No delay needed
        }

        if ($delay > $maxWaitSeconds) {
            Log::warning("Rate limit delay ($delay s) exceeds maximum wait time ($maxWaitSeconds s) for $accountId:$inboxId");

            return false;
        }

        Log::info("Waiting $delay seconds for rate limit reset for $accountId:$inboxId");
        sleep($delay);

        return true;
    }

    /**
     * Reset rate limits for testing or emergency purposes.
     */
    public function resetLimits(string $accountId, string $inboxId): void
    {
        $cache = Cache::store($this->cacheStore);

        foreach (['per_minute', 'per_hour', 'per_day'] as $period) {
            $key = $this->getCacheKey($accountId, $inboxId, $period);
            $cache->forget($key);
        }

        Log::info("Rate limits reset for $accountId:$inboxId");
    }

    /**
     * Get batch processing recommendations based on rate limits.
     */
    public function getBatchRecommendations(string $accountId, string $inboxId, int $totalMessages): array
    {
        $info = $this->getRateLimitInfo($accountId, $inboxId);
        $recommendations = [];

        // Find the most restrictive current limit
        $mostRestrictive = null;
        $minRemaining = PHP_INT_MAX;

        foreach ($info as $period => $data) {
            if ($data['remaining'] < $minRemaining) {
                $minRemaining = $data['remaining'];
                $mostRestrictive = $period;
            }
        }

        if ($mostRestrictive && $minRemaining < $totalMessages) {
            $recommendations[] = [
                'issue'      => 'insufficient_capacity',
                'period'     => $mostRestrictive,
                'available'  => $minRemaining,
                'requested'  => $totalMessages,
                'suggestion' => "Split into batches of $minRemaining messages or wait for rate limit reset",
            ];
        }

        // Suggest optimal batch size
        $optimalBatchSize = $this->calculateOptimalBatchSize($accountId, $inboxId);

        $recommendations[] = [
            'type'               => 'optimization',
            'optimal_batch_size' => $optimalBatchSize,
            'estimated_batches'  => ceil($totalMessages / $optimalBatchSize),
            'suggestion'         => "Process in batches of $optimalBatchSize messages for optimal throughput",
        ];

        return $recommendations;
    }

    /**
     * Calculate optimal batch size based on rate limits.
     */
    public function calculateOptimalBatchSize(string $accountId, string $inboxId): int
    {
        $limits = $this->getRateLimits($accountId, $inboxId);

        // Use the most permissive limit that's reasonable for batch processing
        if (isset($limits['per_minute']) && $limits['per_minute'] <= 100) {
            return max(1, (int) ($limits['per_minute'] * 0.8)); // 80% of minute limit
        }

        if (isset($limits['per_hour']) && $limits['per_hour'] <= 1000) {
            return max(1, min(50, (int) ($limits['per_hour'] * 0.1))); // 10% of hour limit, max 50
        }

        return 25; // Default batch size
    }

    /**
     * Get rate limits for specific account and inbox.
     */
    protected function getRateLimits(string $accountId, string $inboxId): array
    {
        // This would typically come from the inbox configuration
        // For now, we'll use default values
        return [
            'per_minute' => 60,
            'per_hour'   => 1000,
            'per_day'    => 20000,
        ];
    }

    /**
     * Get current count for a specific period.
     */
    protected function getCurrentCount(string $accountId, string $inboxId, string $period): int
    {
        $key = $this->getCacheKey($accountId, $inboxId, $period);

        return Cache::store($this->cacheStore)->get($key, 0);
    }

    /**
     * Increment counter for a specific period.
     */
    protected function incrementPeriodCounter(string $accountId, string $inboxId, string $period, int $ttl): void
    {
        $key = $this->getCacheKey($accountId, $inboxId, $period);
        $cache = Cache::store($this->cacheStore);

        if ($cache->has($key)) {
            $cache->increment($key);
        } else {
            $cache->put($key, 1, $ttl);
        }
    }

    /**
     * Generate cache key for rate limiting.
     */
    protected function getCacheKey(string $accountId, string $inboxId, string $period): string
    {
        $timestamp = $this->getPeriodTimestamp($period);

        return "$this->cachePrefix:$accountId:$inboxId:$period:$timestamp";
    }

    /**
     * Get timestamp for period-based caching.
     */
    protected function getPeriodTimestamp(string $period): string
    {
        $now = Carbon::now();

        return match ($period) {
            'per_minute' => $now->format('Y-m-d-H-i'),
            'per_hour'   => $now->format('Y-m-d-H'),
            'per_day'    => $now->format('Y-m-d'),
            default      => $now->format('Y-m-d-H-i'),
        };
    }

    /**
     * Log rate limit events.
     */
    protected function logRateLimit(string $accountId, string $inboxId, string $event, array $data = []): void
    {
        if ($this->config['logging']['enabled'] ?? true) {
            Log::info("Rate limit event: $event", array_merge([
                'account_id' => $accountId,
                'inbox_id'   => $inboxId,
            ], $data));
        }
    }

    /**
     * Get comprehensive rate limit statistics.
     */
    public function getStatistics(string $accountId, string $inboxId): array
    {
        $info = $this->getRateLimitInfo($accountId, $inboxId);

        return [
            'account_id'         => $accountId,
            'inbox_id'           => $inboxId,
            'limits'             => $info,
            'is_rate_limited'    => $this->isRateLimited($accountId, $inboxId),
            'delay_until_reset'  => $this->getDelayUntilReset($accountId, $inboxId),
            'optimal_batch_size' => $this->calculateOptimalBatchSize($accountId, $inboxId),
            'checked_at'         => Carbon::now()->toISOString(),
        ];
    }
}
