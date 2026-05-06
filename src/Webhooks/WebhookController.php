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
        $accountConfig = $this->config->get("chatwoot.accounts.{$accountName}");

        if (! is_array($accountConfig)) {
            return response()->json(['ok' => false, 'error' => 'unknown_account'], 404);
        }

        $verifySignature = (bool) ($accountConfig['webhook']['verify_signature']
            ?? $this->config->get('chatwoot.webhooks.verify_signature', true));

        if ($verifySignature) {
            $secret = (string) ($accountConfig['webhook']['secret']
                ?? $this->config->get('chatwoot.webhooks.secret', ''));

            $signature = $request->header('X-Chatwoot-Signature')
                ?? $request->header('X-Hub-Signature-256');

            if (! $this->verifier->verify($request->getContent(), is_string($signature) ? $signature : null, $secret)) {
                return response()->json(['ok' => false, 'error' => 'signature_mismatch'], 401);
            }
        }

        $payload = $request->all();
        if ($payload === []) {
            return response()->json(['ok' => false, 'error' => 'empty_payload'], 400);
        }

        $this->dispatcher->dispatch($accountName, WebhookPayload::from($payload));

        return response()->json(['ok' => true]);
    }
}
