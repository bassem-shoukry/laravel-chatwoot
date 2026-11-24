<?php

declare(strict_types=1);

namespace BassamShoukry\LaravelChatwoot\Webhooks;

use BassamShoukry\LaravelChatwoot\Contracts\SignatureVerifier;
use BassamShoukry\LaravelChatwoot\Data\WebhookPayload;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class WebhookController
{
    public function __construct(
        private readonly SignatureVerifier $verifier,
        private readonly WebhookDispatcher $dispatcher,
        private readonly ConfigRepository $config,
    ) {}

    public function __invoke(Request $request, ?string $account = null): JsonResponse
    {
        $accountName = $account ?? (string) $this->config->get('chatwoot.default_account', 'default');

        // Look the account up by array key, never as a dotted config path: a
        // route parameter like "default.webhook" must not resolve a sub-array.
        $accounts = $this->config->get('chatwoot.accounts', []);
        $accountConfig = is_array($accounts) && array_key_exists($accountName, $accounts) ? $accounts[$accountName] : null;

        if (! is_array($accountConfig)) {
            return response()->json(['ok' => false, 'error' => 'unknown_account'], 404);
        }

        /** @var array<string, mixed> $webhook */
        $webhook = is_array($accountConfig['webhook'] ?? null) ? $accountConfig['webhook'] : [];
        $rawBody = $request->getContent();

        $verifySignature = (bool) ($webhook['verify_signature']
            ?? $this->config->get('chatwoot.webhooks.verify_signature', true));

        if ($verifySignature) {
            $rejection = $this->rejectUnlessSigned($request, $rawBody, $webhook);
            if ($rejection !== null) {
                return $rejection;
            }
        }

        // Dispatch only what the signature covers: the raw JSON body. Never
        // $request->all(), which merges unsigned query-string parameters.
        $payload = json_decode($rawBody, true);
        if (! is_array($payload) || $payload === []) {
            return response()->json(['ok' => false, 'error' => 'empty_payload'], 400);
        }

        /** @var array<string, mixed> $payload */
        $this->dispatcher->dispatch($accountName, WebhookPayload::from($payload));

        return response()->json(['ok' => true]);
    }

    /**
     * Chatwoot signs `"{X-Chatwoot-Timestamp}.{raw body}"` with HMAC-SHA256 and
     * sends `X-Chatwoot-Signature: sha256=<hex>`. Requests without a timestamp
     * header fall back to a body-only HMAC (pre-1.2 behaviour).
     *
     * @param array<string, mixed> $webhook
     */
    private function rejectUnlessSigned(Request $request, string $rawBody, array $webhook): ?JsonResponse
    {
        $secret = (string) ($webhook['secret'] ?? $this->config->get('chatwoot.webhooks.secret', ''));

        $signature = $request->header('X-Chatwoot-Signature') ?? $request->header('X-Hub-Signature-256');
        $timestamp = $request->header('X-Chatwoot-Timestamp');
        $timestamp = is_string($timestamp) && $timestamp !== '' ? $timestamp : null;

        $signedContent = $timestamp === null ? $rawBody : "{$timestamp}.{$rawBody}";

        if (! $this->verifier->verify($signedContent, is_string($signature) ? $signature : null, $secret)) {
            return response()->json(['ok' => false, 'error' => 'signature_mismatch'], 401);
        }

        if ($timestamp !== null && ! $this->timestampIsFresh($timestamp, $webhook)) {
            return response()->json(['ok' => false, 'error' => 'stale_timestamp'], 401);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $webhook
     */
    private function timestampIsFresh(string $timestamp, array $webhook): bool
    {
        if (! ctype_digit($timestamp)) {
            return false;
        }

        $tolerance = (int) ($webhook['timestamp_tolerance']
            ?? $this->config->get('chatwoot.webhooks.timestamp_tolerance', 300));

        return $tolerance <= 0 || abs(time() - (int) $timestamp) <= $tolerance;
    }
}
