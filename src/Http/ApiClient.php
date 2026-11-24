<?php

declare(strict_types=1);

namespace BassamShoukry\LaravelChatwoot\Http;

use BassamShoukry\LaravelChatwoot\Contracts\ChatwootClient;
use BassamShoukry\LaravelChatwoot\Exceptions\AuthenticationException;
use BassamShoukry\LaravelChatwoot\Exceptions\ChatwootException;
use BassamShoukry\LaravelChatwoot\Exceptions\NotFoundException;
use BassamShoukry\LaravelChatwoot\Exceptions\RateLimitException;
use BassamShoukry\LaravelChatwoot\Exceptions\ServerException;
use BassamShoukry\LaravelChatwoot\Exceptions\ValidationException;
use BassamShoukry\LaravelChatwoot\Support\AccountContext;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Psr\Log\LoggerInterface;

final class ApiClient implements ChatwootClient
{
    private const IDEMPOTENT_METHODS = ['GET', 'HEAD', 'PUT', 'DELETE', 'OPTIONS'];

    public function __construct(
        private readonly HttpFactory $http,
        private readonly ConfigRepository $config,
        private readonly LoggerInterface $logger,
        private readonly AccountContext $account,
    ) {}

    public function baseUrl(): string
    {
        return $this->account->url;
    }

    public function accountId(): int
    {
        return $this->account->accountId;
    }

    /**
     * @param array<string, mixed> $query
     *
     * @return array<string, mixed>
     */
    public function get(string $path, array $query = []): array
    {
        return $this->dispatch('GET', $path, query: $query);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function post(string $path, array $payload = []): array
    {
        return $this->dispatch('POST', $path, body: $payload);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function patch(string $path, array $payload = []): array
    {
        return $this->dispatch('PATCH', $path, body: $payload);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function put(string $path, array $payload = []): array
    {
        return $this->dispatch('PUT', $path, body: $payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function delete(string $path): array
    {
        return $this->dispatch('DELETE', $path);
    }

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    private function dispatch(string $method, string $path, array $query = [], array $body = []): array
    {
        $url = $this->absoluteUrl($path);

        $attempt = 0;
        $maxAttempts = max(1, (int) $this->config->get('chatwoot.api.retry_attempts', 3));
        $baseDelay = max(0, (int) $this->config->get('chatwoot.api.retry_delay', 1000));

        // A POST/PATCH that hit a 5xx or a timeout may already have been
        // processed by Chatwoot; retrying it can send a customer the same
        // message twice. Only idempotent methods are retried on those errors
        // unless the app opts in. A 429 is retried for every method.
        $retryServerErrors = in_array($method, self::IDEMPOTENT_METHODS, true)
            || (bool) $this->config->get('chatwoot.api.retry_non_idempotent', false);

        while (true) {
            $attempt++;

            try {
                $response = $this->newRequest()->send($method, $url, $this->buildOptions($query, $body));
            } catch (ConnectionException $e) {
                if (! $retryServerErrors || $attempt >= $maxAttempts) {
                    throw new ServerException("Chatwoot connection failed: {$e->getMessage()}", 0, $e);
                }
                $this->sleep($baseDelay * $attempt);

                continue;
            }

            $this->log($method, $url, $body, $response);

            if ($response->successful()) {
                return $this->decodeSuccessful($response, $method, $url);
            }

            if ($this->shouldRetry($response, $attempt, $maxAttempts, $retryServerErrors)) {
                $this->sleep($this->retryDelay($response, $baseDelay, $attempt));

                continue;
            }

            $this->throwForStatus($response, $method, $url);
        }
    }

    private function newRequest(): PendingRequest
    {
        $timeout = (int) $this->config->get('chatwoot.api.timeout', 30);
        $userAgent = (string) $this->config->get('chatwoot.api.user_agent', 'laravel-chatwoot/1.0');

        return $this->http
            ->withToken($this->account->token, '')
            ->withHeaders([
                'api_access_token' => $this->account->token,
                'Accept'           => 'application/json',
                'Content-Type'     => 'application/json',
                'User-Agent'       => $userAgent,
            ])
            ->timeout($timeout)
            // Guzzle strips Authorization on a cross-host redirect but keeps
            // the custom api_access_token header; the API never needs redirects.
            ->withoutRedirecting()
            ->acceptJson()
            ->asJson();
    }

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    private function buildOptions(array $query, array $body): array
    {
        $options = [];
        if ($query !== []) {
            $options['query'] = $query;
        }
        if ($body !== []) {
            $options['json'] = $body;
        }

        return $options;
    }

    private function absoluteUrl(string $path): string
    {
        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $path) === 1) {
            // Every request carries the account token, so an absolute URL is
            // only allowed on the account's own origin (scheme, host, port).
            if ($this->origin($path) !== $this->origin($this->account->url)) {
                throw new ChatwootException(sprintf(
                    'Refusing to send the Chatwoot token to a different origin than the account URL [%s].',
                    $this->origin($this->account->url),
                ));
            }

            return $path;
        }

        $path = ltrim($path, '/');

        return $this->account->url . '/' . $path;
    }

    private function origin(string $url): string
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = strtolower(rtrim((string) parse_url($url, PHP_URL_HOST), '.'));
        $port = parse_url($url, PHP_URL_PORT) ?? match ($scheme) {
            'http'  => 80,
            'https' => 443,
            default => null,
        };

        return "{$scheme}://{$host}:{$port}";
    }

    private function shouldRetry(Response $response, int $attempt, int $maxAttempts, bool $retryServerErrors): bool
    {
        if ($attempt >= $maxAttempts) {
            return false;
        }

        $status = $response->status();

        if ($status === 429) {
            // Never block a worker on a Retry-After beyond the configured cap;
            // surface the RateLimitException (with retryAfter) to the caller.
            $retryAfter = $this->retryAfterSeconds($response);

            return $retryAfter === null || $retryAfter <= $this->maxRetryAfter();
        }

        return $retryServerErrors && $status >= 500;
    }

    private function retryDelay(Response $response, int $baseDelay, int $attempt): int
    {
        if ($response->status() === 429) {
            $retryAfter = $this->retryAfterSeconds($response);
            if ($retryAfter !== null && $retryAfter > 0) {
                return $retryAfter * 1000;
            }
        }

        return $baseDelay * $attempt;
    }

    private function maxRetryAfter(): int
    {
        return max(0, (int) $this->config->get('chatwoot.api.max_retry_after', 60));
    }

    /**
     * Retry-After is either delta-seconds or an HTTP-date (RFC 9110 §10.2.3).
     */
    private function retryAfterSeconds(Response $response): ?int
    {
        $header = trim($response->header('Retry-After'));
        if ($header === '') {
            return null;
        }

        if (ctype_digit($header)) {
            return (int) $header;
        }

        $timestamp = strtotime($header);

        return $timestamp === false ? null : max(0, $timestamp - time());
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeSuccessful(Response $response, string $method, string $url): array
    {
        $raw = trim($response->body());
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            // e.g. an HTML page from a proxy or captive portal: failing loudly
            // beats returning a Message with id 0.
            // No context on purpose: callers treat an empty context as "no
            // usable HTTP error body" and fall back to this message.
            throw new ChatwootException(sprintf(
                'Chatwoot %s %s returned HTTP %d with a body that is not valid JSON.',
                $method,
                $url,
                $response->status(),
            ));
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Response $response): array
    {
        if ($response->body() === '') {
            return [];
        }

        $decoded = $response->json();

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $body
     */
    private function log(string $method, string $url, array $body, Response $response): void
    {
        if (! (bool) $this->config->get('chatwoot.logging.enabled', false)) {
            return;
        }

        $context = [
            'method'   => $method,
            'url'      => $url,
            'status'   => $response->status(),
            'duration' => $response->transferStats?->getTransferTime(),
        ];

        if ((bool) $this->config->get('chatwoot.logging.log_request_body', false) && $body !== []) {
            $context['request'] = LogScrubber::body($body);
        }

        if ((bool) $this->config->get('chatwoot.logging.log_response_body', false)) {
            $context['response'] = LogScrubber::body($this->decode($response));
        }

        $this->logger->info('chatwoot.request', $context);
    }

    private function sleep(int $milliseconds): void
    {
        if ($milliseconds <= 0) {
            return;
        }
        usleep($milliseconds * 1000);
    }

    /**
     * @throws ChatwootException
     */
    private function throwForStatus(Response $response, string $method, string $url): never
    {
        $status = $response->status();
        $message = sprintf('Chatwoot %s %s failed with HTTP %d.', $method, $url, $status);
        $body = $this->decode($response);

        $exception = match (true) {
            $status === 401 || $status === 403 => new AuthenticationException($message),
            $status === 404                    => new NotFoundException($message),
            $status === 422                    => new ValidationException($message),
            $status === 429                    => (new RateLimitException($message))->withRetryAfter(
                $this->retryAfterSeconds($response) ?: null,
            ),
            $status >= 500 => new ServerException($message),
            default        => new ChatwootException($message),
        };

        // Laravel's exception handler logs context(); keep secrets out of it.
        throw $exception->withContext(['status' => $status, 'body' => LogScrubber::body($body)]);
    }
}
