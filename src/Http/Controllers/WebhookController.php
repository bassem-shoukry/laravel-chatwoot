<?php

namespace BassamShoukry\LaravelChatwoot\Http\Controllers;

use BassamShoukry\LaravelChatwoot\Services\WebhookHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class WebhookController extends Controller
{
    protected WebhookHandler $webhookHandler;

    public function __construct(WebhookHandler $webhookHandler)
    {
        $this->webhookHandler = $webhookHandler;
    }

    /**
     * Handle incoming webhooks from Chatwoot.
     */
    public function handle(Request $request): JsonResponse
    {
        try {
            $result = $this->webhookHandler->handle($request);

            $statusCode = $result['success'] ? 200 : 400;

            return response()->json($result, $statusCode);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error'   => 'Internal server error',
                'message' => 'Webhook processing failed',
            ], 500);
        }
    }

    /**
     * Health check endpoint for webhook configuration.
     */
    public function health(): JsonResponse
    {
        return response()->json([
            'status'    => 'ok',
            'service'   => 'laravel-chatwoot-webhooks',
            'timestamp' => now()->toISOString(),
            'version'   => '1.0.0',
        ]);
    }

    /**
     * Get webhook statistics.
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            $accountId = $request->query('account_id');
            $days = (int) $request->query('days', 30);

            $stats = $this->webhookHandler->getStatistics($accountId, $days);

            return response()->json([
                'success'      => true,
                'statistics'   => $stats,
                'generated_at' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
