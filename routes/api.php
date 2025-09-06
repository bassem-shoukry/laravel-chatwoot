<?php

use BassamShoukry\LaravelChatwoot\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Laravel Chatwoot API Routes
|--------------------------------------------------------------------------
|
| Here are the routes for handling incoming webhooks from Chatwoot.
| These routes are automatically registered by the service provider
| and are prefixed with 'chatwoot' by default.
|
*/

// Webhook routes
Route::group(['prefix' => 'webhooks', 'as' => 'webhooks.'], function () {
    // Main webhook handler - receives events from Chatwoot
    Route::post('/', [WebhookController::class, 'handle'])->name('handle');

    // Health check endpoint
    Route::get('/health', [WebhookController::class, 'health'])->name('health');

    // Webhook statistics endpoint
    Route::get('/statistics', [WebhookController::class, 'statistics'])->name('statistics');
});
