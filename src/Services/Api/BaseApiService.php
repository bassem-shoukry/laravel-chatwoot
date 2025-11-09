<?php

namespace BassamShoukry\LaravelChatwoot\Services\Api;

use BassamShoukry\LaravelChatwoot\Exceptions\ChatwootApiException;
use BassamShoukry\LaravelChatwoot\Services\AccountManager;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

abstract class BaseApiService
{
    protected AccountManager $accountManager;
    protected array $config;

    public function __construct(AccountManager $accountManager)
    {
        $this->accountManager = $accountManager;
        $this->config = config('chatwoot.api', []);
    }

    /**
     * Make HTTP request to Chatwoot API.
     */
    protected function makeRequest(string $method, string $endpoint, array $data = [], array $params = []): array
    {
        $accountInfo = $this->accountManager->getCurrentAccountInfo();

        if (! $accountInfo) {
            throw new ChatwootApiException('No account context set. Call account() method first.');
        }

        $url = rtrim($accountInfo['url'], '/') . '/api/v1/' . ltrim($endpoint, '/');
        $token = $accountInfo['token'];

        if (! $token) {
            throw new ChatwootApiException('No API token available for current account');
        }

        // Add query parameters to URL
        if (! empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $timeout = $this->config['timeout'] ?? 30;
        $retryAttempts = $this->config['retry_attempts'] ?? 3;
        $retryDelay = $this->config['retry_delay'] ?? 1000; // milliseconds

        $lastException = null;

        for ($attempt = 1; $attempt <= $retryAttempts; $attempt++) {
            try {
                Log::debug("Chatwoot API request attempt $attempt", [
                    'method' => $method,
                    'url'    => $url,
                    'data'   => $data,
                    'params' => $params,
                ]);

                $response = Http::timeout($timeout)
                    ->withHeaders([
                        'api_access_token' => $token,
                        'Content-Type'     => 'application/json',
                        'Accept'           => 'application/json',
                    ])
                    ->when($method === 'GET', fn ($http) => $http->get($url))
                    ->when($method === 'POST', fn ($http) => $http->post($url, $data))
                    ->when($method === 'PUT', fn ($http) => $http->put($url, $data))
                    ->when($method === 'PATCH', fn ($http) => $http->patch($url, $data))
                    ->when($method === 'DELETE', fn ($http) => $http->delete($url, $data));

                if ($response->successful()) {
                    $responseData = $response->json() ?? [];

                    Log::info('Chatwoot API request successful', [
                        'method'   => $method,
                        'endpoint' => $endpoint,
                        'status'   => $response->status(),
                        'attempt'  => $attempt,
                    ]);

                    return $responseData;
                }

                // Handle specific error cases
                $statusCode = $response->status();
                $errorData = $response->json();
                $errorMessage = $errorData['message'] ?? $response->body();

                // Don't retry on certain status codes
                if (in_array($statusCode, [400, 401, 403, 404, 422])) {
                    throw new ChatwootApiException(
                        "API request failed with status $statusCode: $errorMessage",
                        $statusCode,
                        $errorData
                    );
                }

                // Retry on server errors or network issues
                $lastException = new ChatwootApiException(
                    "API request failed with status $statusCode: $errorMessage",
                    $statusCode,
                    $errorData
                );

                if ($attempt < $retryAttempts) {
                    Log::warning('Chatwoot API request failed, retrying', [
                        'method'      => $method,
                        'endpoint'    => $endpoint,
                        'status'      => $statusCode,
                        'attempt'     => $attempt,
                        'retry_in_ms' => $retryDelay,
                    ]);

                    usleep($retryDelay * 1000); // Convert to microseconds
                    $retryDelay *= 2; // Exponential backoff
                }

            } catch (ChatwootApiException $e) {
                throw $e; // Re-throw API exceptions immediately
            } catch (\Exception $e) {
                $lastException = new ChatwootApiException(
                    'Network error: ' . $e->getMessage(),
                    0,
                    null,
                    $e
                );

                if ($attempt < $retryAttempts) {
                    Log::warning('Chatwoot API network error, retrying', [
                        'method'   => $method,
                        'endpoint' => $endpoint,
                        'error'    => $e->getMessage(),
                        'attempt'  => $attempt,
                    ]);

                    usleep($retryDelay * 1000);
                    $retryDelay *= 2;
                }
            }
        }

        // All retry attempts failed
        Log::error("Chatwoot API request failed after $retryAttempts attempts", [
            'method'     => $method,
            'endpoint'   => $endpoint,
            'last_error' => $lastException->getMessage(),
        ]);

        throw $lastException;
    }

    /**
     * Build full URL for endpoint.
     */
    protected function buildUrl(string $baseUrl, string $endpoint): string
    {
        return rtrim($baseUrl, '/') . '/api/v1/' . ltrim($endpoint, '/');
    }

    /**
     * Get current account context.
     */
    protected function getCurrentAccount(): array
    {
        $accountInfo = $this->accountManager->getCurrentAccountInfo();

        if (! $accountInfo) {
            throw new ChatwootApiException('No account context set. Call account() method first.');
        }

        return $accountInfo;
    }

    /**
     * Validate required fields in data array.
     */
    protected function validateRequiredFields(array $data, array $requiredFields): void
    {
        $missing = [];

        foreach ($requiredFields as $field) {
            if (! isset($data[$field]) || $data[$field] === null || $data[$field] === '') {
                $missing[] = $field;
            }
        }

        if (! empty($missing)) {
            throw new \InvalidArgumentException(
                'Missing required fields: ' . implode(', ', $missing)
            );
        }
    }

    /**
     * Filter data to only include allowed fields.
     */
    protected function filterAllowedFields(array $data, array $allowedFields): array
    {
        return array_intersect_key($data, array_flip($allowedFields));
    }

    /**
     * Get paginated results from an endpoint.
     */
    protected function paginate(string $endpoint, int $page = 1, int $perPage = 25, array $additionalParams = []): array
    {
        $params = array_merge($additionalParams, [
            'page'     => $page,
            'per_page' => min($perPage, 100), // Limit to reasonable page size
        ]);

        return $this->makeRequest('GET', $endpoint, [], $params);
    }

    /**
     * Fetch all results from a paginated endpoint automatically.
     *
     * @param string $endpoint API endpoint to fetch from
     * @param array $params Additional query parameters
     * @param int $perPage Number of items per page (max 100)
     * @param int $maxPages Safety limit for maximum pages (default 100)
     * @param string $payloadKey Key in response containing the items (default 'payload')
     * @return array All fetched items
     */
    protected function fetchAll(
        string $endpoint,
        array $params = [],
        int $perPage = 100,
        int $maxPages = 100,
        string $payloadKey = 'payload'
    ): array {
        $allItems = [];
        $page = 1;

        do {
            $response = $this->paginate($endpoint, $page, $perPage, $params);
            $items = $response[$payloadKey] ?? [];

            $allItems = array_merge($allItems, $items);

            $hasMore = count($items) === $perPage;
            $page++;

        } while ($hasMore && $page <= $maxPages);

        return $allItems;
    }
}
