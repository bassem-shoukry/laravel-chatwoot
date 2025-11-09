<?php

namespace BassamShoukry\LaravelChatwoot\Services;

use BassamShoukry\LaravelChatwoot\Exceptions\ChatwootApiException;
use BassamShoukry\LaravelChatwoot\Exceptions\InvalidTokenException;
use BassamShoukry\LaravelChatwoot\Services\Api\AccountsApi;
use BassamShoukry\LaravelChatwoot\Services\Api\ContactsApi;
use BassamShoukry\LaravelChatwoot\Services\Api\ConversationsApi;
use BassamShoukry\LaravelChatwoot\Services\Api\InboxesApi;
use BassamShoukry\LaravelChatwoot\Services\Api\MessagesApi;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

class ApiClient
{
    protected array $config;
    protected AccountManager $accountManager;
    public AccountsApi $accounts;
    public ContactsApi $contacts;
    public ConversationsApi $conversations;
    public InboxesApi $inboxes;
    public MessagesApi $messages;

    public function __construct(?AccountManager $accountManager = null)
    {
        $this->config = Config::get('chatwoot', []);
        $this->accountManager = $accountManager ?? app(AccountManager::class);

        // Initialize API service instances
        $this->accounts = new AccountsApi($this->accountManager);
        $this->contacts = new ContactsApi($this->accountManager);
        $this->conversations = new ConversationsApi($this->accountManager);
        $this->inboxes = new InboxesApi($this->accountManager);
        $this->messages = new MessagesApi($this->accountManager);
    }

    /**
     * Make a GET request to the Chatwoot API.
     *
     * @throws ChatwootApiException
     */
    public function get(string $url, string $token, array $params = []): array
    {
        return $this->makeRequest('GET', $url, $token, [], $params);
    }

    /**
     * Make a POST request to the Chatwoot API.
     *
     * @throws ChatwootApiException
     */
    public function post(string $url, string $token, array $data = []): array
    {
        return $this->makeRequest('POST', $url, $token, $data);
    }

    /**
     * Make a PUT request to the Chatwoot API.
     *
     * @throws ChatwootApiException
     */
    public function put(string $url, string $token, array $data = []): array
    {
        return $this->makeRequest('PUT', $url, $token, $data);
    }

    /**
     * Make a PATCH request to the Chatwoot API.
     */
    public function patch(string $url, string $token, array $data = []): array
    {
        return $this->makeRequest('PATCH', $url, $token, $data);
    }

    /**
     * Make a DELETE request to the Chatwoot API.
     */
    public function delete(string $url, string $token): array
    {
        return $this->makeRequest('DELETE', $url, $token);
    }

    /**
     * Send a message through Chatwoot API.
     */
    public function sendMessage(string $baseUrl, string $token, string $accountId, string $conversationId, array $messageData): array
    {
        $url = $this->buildUrl($baseUrl, "api/v1/accounts/$accountId/conversations/$conversationId/messages");

        return $this->post($url, $token, $messageData);
    }

    /**
     * Create a conversation.
     */
    public function createConversation(string $baseUrl, string $token, string $accountId, array $conversationData): array
    {
        $url = $this->buildUrl($baseUrl, "api/v1/accounts/$accountId/conversations");

        return $this->post($url, $token, $conversationData);
    }

    /**
     * Get conversation details.
     */
    public function getConversation(string $baseUrl, string $token, string $accountId, string $conversationId): array
    {
        $url = $this->buildUrl($baseUrl, "api/v1/accounts/$accountId/conversations/$conversationId");

        return $this->get($url, $token);
    }

    /**
     * Get conversations list.
     */
    public function getConversations(string $baseUrl, string $token, string $accountId, array $params = []): array
    {
        $url = $this->buildUrl($baseUrl, "api/v1/accounts/$accountId/conversations");

        return $this->get($url, $token, $params);
    }

    /**
     * Create a contact.
     */
    public function createContact(string $baseUrl, string $token, string $accountId, array $contactData): array
    {
        $url = $this->buildUrl($baseUrl, "api/v1/accounts/$accountId/contacts");

        return $this->post($url, $token, $contactData);
    }

    /**
     * Get contact details.
     */
    public function getContact(string $baseUrl, string $token, string $accountId, string $contactId): array
    {
        $url = $this->buildUrl($baseUrl, "api/v1/accounts/$accountId/contacts/$contactId");

        return $this->get($url, $token);
    }

    /**
     * Update contact.
     */
    public function updateContact(string $baseUrl, string $token, string $accountId, string $contactId, array $contactData): array
    {
        $url = $this->buildUrl($baseUrl, "api/v1/accounts/$accountId/contacts/$contactId");

        return $this->patch($url, $token, $contactData);
    }

    /**
     * Get inboxes list.
     */
    public function getInboxes(string $baseUrl, string $token, string $accountId): array
    {
        $url = $this->buildUrl($baseUrl, "api/v1/accounts/$accountId/inboxes");

        return $this->get($url, $token);
    }

    /**
     * Get agents list.
     */
    public function getAgents(string $baseUrl, string $token, string $accountId): array
    {
        $url = $this->buildUrl($baseUrl, "api/v1/accounts/$accountId/agents");

        return $this->get($url, $token);
    }

    /**
     * Get account details.
     */
    public function getAccount(string $baseUrl, string $token, string $accountId): array
    {
        $url = $this->buildUrl($baseUrl, "api/v1/accounts/$accountId");

        return $this->get($url, $token);
    }

    /**
     * Test API connection.
     */
    public function testConnection(string $baseUrl, string $token, string $accountId): array
    {
        try {
            $result = $this->getAccount($baseUrl, $token, $accountId);

            return [
                'success' => true,
                'account' => $result,
                'message' => 'Connection successful',
            ];
        } catch (ChatwootApiException $e) {
            return [
                'success'       => false,
                'error'         => $e->getMessage(),
                'http_code'     => $e->getHttpCode(),
                'response_data' => $e->getResponseData(),
            ];
        }
    }

    /**
     * Make HTTP request with error handling and retries.
     */
    protected function makeRequest(string $method, string $url, string $token, array $data = [], array $params = []): array
    {
        $retryAttempts = $this->config['api']['retry_times'] ?? 3;
        $retryDelay = $this->config['api']['retry_delay'] ?? 1000; // milliseconds

        for ($attempt = 1; $attempt <= $retryAttempts; $attempt++) {
            try {
                $response = $this->executeRequest($method, $url, $token, $data, $params);

                if ($response->successful()) {
                    return $response->json() ?? [];
                }

                // If this is the last attempt, throw exception
                if ($attempt === $retryAttempts) {
                    $this->handleApiError($response);
                }

                // Wait before retry
                if ($attempt < $retryAttempts) {
                    usleep($retryDelay * 1000 * $attempt); // exponential backoff
                }

            } catch (ChatwootApiException $e) {
                if ($attempt === $retryAttempts) {
                    throw $e;
                }

                if ($attempt < $retryAttempts) {
                    usleep($retryDelay * 1000 * $attempt);
                }
            }
        }

        throw new ChatwootApiException('Maximum retry attempts exceeded');
    }

    /**
     * Execute the actual HTTP request.
     */
    protected function executeRequest(string $method, string $url, string $token, array $data = [], array $params = []): Response
    {
        $client = Http::withHeaders([
            'Authorization' => "Bearer $token",
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
            'User-Agent'    => $this->config['api']['user_agent'] ?? 'Laravel-Chatwoot-Package/1.0',
        ])
            ->timeout($this->config['api']['timeout'] ?? 30)
            ->connectTimeout($this->config['api']['connect_timeout'] ?? 10);

        // Add SSL verification setting
        if (isset($this->config['api']['verify_ssl']) && ! $this->config['api']['verify_ssl']) {
            $client->withoutVerifying();
        }

        $response = match (strtoupper($method)) {
            'GET'    => $client->get($url, $params),
            'POST'   => $client->post($url, $data),
            'PUT'    => $client->put($url, $data),
            'PATCH'  => $client->patch($url, $data),
            'DELETE' => $client->delete($url),
            default  => throw new \InvalidArgumentException("Unsupported HTTP method: $method")
        };

        return $response;
    }

    /**
     * Handle API errors and throw appropriate exceptions.
     */
    protected function handleApiError(Response $response): void
    {
        $statusCode = $response->status();
        $responseBody = $response->json() ?? [];
        $errorMessage = $responseBody['message'] ?? $responseBody['error'] ?? 'Chatwoot API error';

        switch ($statusCode) {
            case 401:
                throw new InvalidTokenException($errorMessage, 401, $statusCode, $responseBody);

            case 403:
                throw new ChatwootApiException('Forbidden: ' . $errorMessage, 403, $statusCode, $responseBody);

            case 404:
                throw new ChatwootApiException('Not found: ' . $errorMessage, 404, $statusCode, $responseBody);

            case 422:
                throw new ChatwootApiException('Validation error: ' . $errorMessage, 422, $statusCode, $responseBody);

            case 429:
                throw new ChatwootApiException('Rate limit exceeded: ' . $errorMessage, 429, $statusCode, $responseBody);

            case 500:
            case 502:
            case 503:
            case 504:
                throw new ChatwootApiException('Server error: ' . $errorMessage, 500, $statusCode, $responseBody);

            default:
                throw new ChatwootApiException(
                    "HTTP $statusCode: $errorMessage",
                    $statusCode,
                    $statusCode,
                    $responseBody
                );
        }
    }

    /**
     * Build complete URL from base URL and endpoint.
     */
    public function buildUrl(string $baseUrl, string $endpoint): string
    {
        $baseUrl = rtrim($baseUrl, '/');
        $endpoint = ltrim($endpoint, '/');

        return "$baseUrl/$endpoint";
    }

    /**
     * Get client configuration.
     */
    public function getConfig(): array
    {
        return $this->config['api'] ?? [];
    }

    /**
     * Update client configuration.
     */
    public function setConfig(array $config): void
    {
        $this->config['api'] = array_merge($this->config['api'] ?? [], $config);
    }

    /**
     * Check if request logging is enabled.
     */
    public function isRequestLoggingEnabled(): bool
    {
        return $this->config['logging']['log_requests'] ?? false;
    }

    /**
     * Check if response logging is enabled.
     */
    public function isResponseLoggingEnabled(): bool
    {
        return $this->config['logging']['log_responses'] ?? false;
    }

    /**
     * Get request timeout setting.
     */
    public function getTimeout(): int
    {
        return $this->config['api']['timeout'] ?? 30;
    }

    /**
     * Get connection timeout setting.
     */
    public function getConnectTimeout(): int
    {
        return $this->config['api']['connect_timeout'] ?? 10;
    }

    /**
     * Get retry settings.
     */
    public function getRetrySettings(): array
    {
        return [
            'times' => $this->config['api']['retry_times'] ?? 3,
            'delay' => $this->config['api']['retry_delay'] ?? 1000,
        ];
    }
}
